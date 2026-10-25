<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution\ModelsLoader;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Pagination\PaginationArgs;
use Nuwave\Lighthouse\Pagination\ZeroPerPageLengthAwarePaginator;
use Nuwave\Lighthouse\Support\Utils;

class PaginatedModelsLoader implements ModelsLoader
{
    public function __construct(
        protected string $relation,
        protected \Closure $decorateBuilder,
        protected PaginationArgs $paginationArgs,
        /** Can $decorateBuilder constrain the query differently depending on the given parent model? */
        protected bool $decorationDependsOnParent = true,
    ) {}

    public function load(EloquentCollection $parents): void
    {
        // When all items are requested, counting the loaded models is sufficient
        if ($this->paginationArgs->first !== null) {
            $parents->loadCount([$this->relation => $this->decorateBuilder]);
        }

        $relation = $this->relationInstance($parents);
        $relatedModels = $this->loadRelatedModels($parents);

        $this->hydratePivotRelation($relation, $relatedModels);
        $this->loadDefaultWith($relatedModels);
        $this->associateRelationModels($parents, $relatedModels);
        $this->convertRelationToPaginator($parents);
    }

    public function extract(Model $model): mixed
    {
        return $model->getRelation($this->relation);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<array-key, \Illuminate\Database\Eloquent\Model>  $parents
     *
     * @return \Illuminate\Database\Eloquent\Collection<array-key, \Illuminate\Database\Eloquent\Model>
     */
    protected function loadRelatedModels(EloquentCollection $parents): EloquentCollection
    {
        $first = $this->paginationArgs->first;

        $parentGroups = new Collection(
            $first === null
                ? $this->groupParentsByConstraints($parents)
                // Each parent is limited separately
                : array_map(static fn (Model $model): array => [$model], $parents->all()),
        );

        $relations = $parentGroups
            ->map(function (array $models) use ($parents, $parentGroups, $first): Relation {
                $relation = $this->relationInstance($parents);

                $relation->addEagerConstraints($models);

                ($this->decorateBuilder)($relation, $models[0]);

                if ($relation instanceof BelongsToMany || $relation instanceof HasManyThrough) {
                    $select = Utils::callProtected($relation, 'shouldSelect', ['*']);
                    $relation->addSelect($select);
                }

                $relation->initRelation($models, $this->relation);

                if ($first === null && $parentGroups->count() === 1) {
                    return $relation;
                }

                // Unlimited queries combined with UNION ALL below still need a limit, otherwise MySQL ignores their ORDER BY
                return $relation->forPage($this->paginationArgs->page, $first ?? PHP_INT_MAX);
            });

        // Merge all the relation queries into a single query with UNION ALL.

        $firstRelation = $relations->shift();
        assert($firstRelation instanceof Relation, 'Non-null because only non-empty lists of parents are passed into this loader.');

        // Use ->getQuery() to respect model scopes, such as soft deletes
        $mergedRelationQuery = $relations->reduce(
            static fn (EloquentBuilder $builder, Relation $relation): EloquentBuilder => $builder->unionAll(
                $relation->getQuery(),
            ),
            $firstRelation->getQuery(),
        );

        $relatedModels = $mergedRelationQuery->get();

        return $relatedModels->unique(
            // Compare all attributes because there might not be a unique primary key
            // or there could be differing pivot attributes.
            static fn (Model $relatedModel): string => serialize($relatedModel->getRawOriginal()),
        );
    }

    /**
     * Group parents whose related models can be loaded with the same constraints.
     *
     * Builder directives may constrain the query differently for each parent.
     * Usually they do not, which allows loading the related models of all parents in a single query.
     *
     * @see \Tests\Integration\Schema\Directives\BuilderDirectiveTest::testCallsCustomBuilderMethodOnFieldWithSpecificModel
     *
     * @param  \Illuminate\Database\Eloquent\Collection<array-key, \Illuminate\Database\Eloquent\Model>  $parents
     *
     * @return list<list<\Illuminate\Database\Eloquent\Model>>
     */
    protected function groupParentsByConstraints(EloquentCollection $parents): array
    {
        if (! $this->decorationDependsOnParent) {
            return [array_values($parents->all())];
        }

        /** @var array<string, list<\Illuminate\Database\Eloquent\Model>> $groups */
        $groups = [];

        foreach ($parents as $model) {
            $relation = $this->relationInstance($parents);
            ($this->decorateBuilder)($relation, $model);

            $query = $relation->getQuery()->toBase();

            // Compare bindings as they are executed, e.g. dates that only differ in microseconds may be formatted equally
            $bindings = $query->getConnection()->prepareBindings($query->getBindings());

            $groups[serialize([$query->toSql(), $bindings])][] = $model;
        }

        return array_values($groups);
    }

    /**
     * Use the underlying model to instantiate a relation by name.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<array-key, \Illuminate\Database\Eloquent\Model>  $parents
     *
     * @return \Illuminate\Database\Eloquent\Relations\Relation<\Illuminate\Database\Eloquent\Model>
     */
    protected function relationInstance(EloquentCollection $parents): Relation
    {
        return $this->newModelQuery($parents)
            ->getRelation($this->relation);
    }

    /**
     * Return a fresh instance of a query builder for the underlying model.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<array-key, \Illuminate\Database\Eloquent\Model>  $parents
     *
     * @return \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected function newModelQuery(EloquentCollection $parents): EloquentBuilder
    {
        $anyModelInstance = $parents->first();
        assert($anyModelInstance instanceof Model);

        // @phpstan-ignore-next-line Laravel 9 defines this as Builder|Model
        return $anyModelInstance->newModelQuery();
    }

    /**
     * Ensure the pivot relation is hydrated too, if it exists.
     *
     * @param  \Illuminate\Database\Eloquent\Relations\Relation<\Illuminate\Database\Eloquent\Model>  $relation
     * @param  \Illuminate\Database\Eloquent\Collection<array-key, \Illuminate\Database\Eloquent\Model>  $relatedModels
     */
    protected function hydratePivotRelation(Relation $relation, EloquentCollection $relatedModels): void
    {
        /**
         * @see BelongsToMany::hydratePivotRelation()
         */
        if ($relation instanceof BelongsToMany) {
            Utils::callProtected($relation, 'hydratePivotRelation', $relatedModels->all());
        }
    }

    /**
     * Ensure the models default relations are loaded.
     *
     * This is necessary because we load models in a non-standard way in @see loadRelatedModels()
     *
     * @param  \Illuminate\Database\Eloquent\Collection<array-key, \Illuminate\Database\Eloquent\Model>  $models
     */
    protected function loadDefaultWith(EloquentCollection $models): void
    {
        $model = $models->first();
        if ($model === null) {
            return;
        }

        $unloadedWiths = array_filter(
            Utils::accessProtected($model, 'with'),
            static fn (string $relation): bool => ! $model->relationLoaded($relation),
        );

        if ($unloadedWiths !== []) {
            $models->load($unloadedWiths);
        }
    }

    /**
     * Associate the collection of all fetched relationModels back with their parents.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<array-key, \Illuminate\Database\Eloquent\Model>  $parents
     * @param  \Illuminate\Database\Eloquent\Collection<array-key, \Illuminate\Database\Eloquent\Model>  $relatedModels
     */
    protected function associateRelationModels(EloquentCollection $parents, EloquentCollection $relatedModels): void
    {
        $this->relationInstance($parents)
            ->match(
                $parents->all(),
                $relatedModels,
                $this->relation,
            );
    }

    /** @param  \Illuminate\Database\Eloquent\Collection<array-key, \Illuminate\Database\Eloquent\Model>  $parents */
    protected function convertRelationToPaginator(EloquentCollection $parents): void
    {
        $first = $this->paginationArgs->first;
        $page = $this->paginationArgs->page;

        foreach ($parents as $model) {
            if ($first === null) {
                $relatedModels = $model->getRelation($this->relation);
                assert($relatedModels instanceof EloquentCollection);

                $total = $relatedModels->count();
                $paginator = new LengthAwarePaginator($relatedModels, $total, max(1, $total), $page);
            } else {
                $total = CountModelsLoader::extractCount($model, $this->relation);

                $paginator = $first === 0
                    ? new ZeroPerPageLengthAwarePaginator($total, $page)
                    : new LengthAwarePaginator($model->getRelation($this->relation), $total, $first, $page);
            }

            $model->setRelation($this->relation, $paginator);
        }
    }
}
