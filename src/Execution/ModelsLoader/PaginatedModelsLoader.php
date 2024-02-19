<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution\ModelsLoader;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Nuwave\Lighthouse\Pagination\PaginationArgs;
use Nuwave\Lighthouse\Pagination\ZeroPerPageLengthAwarePaginator;
use Nuwave\Lighthouse\Support\Utils;

class PaginatedModelsLoader implements ModelsLoader
{
    public function __construct(
        protected string $relation,
        protected \Closure $decorateBuilder,
        protected PaginationArgs $paginationArgs,
    ) {}

    public function load(EloquentCollection $parents): void
    {
        $parents->loadCount([$this->relation => $this->decorateBuilder]);

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
     * @param  \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model>  $parents
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model>
     */
    protected function loadRelatedModels(EloquentCollection $parents): EloquentCollection
    {
        $relations = $parents
            ->toBase()
            ->map(function (Model $model) use ($parents): Relation {
                $relation = $this->relationInstance($parents);

                $relation->addEagerConstraints([$model]);

                ($this->decorateBuilder)($relation, $model);

                if ($relation instanceof BelongsToMany || $relation instanceof HasManyThrough) {
                    $select = Utils::callProtected($relation, 'shouldSelect', ['*']);
                    $relation->addSelect($select);
                }

                $relation->initRelation([$model], $this->relation);

                // @phpstan-ignore-next-line Builder mixin is not understood
                return $relation->forPage($this->paginationArgs->page, $this->paginationArgs->first);
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
            static fn (Model $relatedModel): string => $relatedModel->toJson(),
        );
    }

    /**
     * Use the underlying model to instantiate a relation by name.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model>  $parents
     *
     * @return \Illuminate\Database\Eloquent\Relations\Relation<\Illuminate\Database\Eloquent\Model>
     */
    protected function relationInstance(EloquentCollection $parents): Relation
    {
        return $this
            ->newModelQuery($parents)
            ->getRelation($this->relation);
    }

    /**
     * Return a fresh instance of a query builder for the underlying model.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model>  $parents
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
     * @param  \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model>  $relatedModels
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
     * @param  \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model>  $models
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
     * @param  \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model>  $parents
     * @param  \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model>  $relatedModels
     */
    protected function associateRelationModels(EloquentCollection $parents, EloquentCollection $relatedModels): void
    {
        $this
            ->relationInstance($parents)
            ->match(
                $parents->all(),
                $relatedModels,
                $this->relation,
            );
    }

    /** @param  \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model>  $parents */
    protected function convertRelationToPaginator(EloquentCollection $parents): void
    {
        $first = $this->paginationArgs->first;
        $page = $this->paginationArgs->page;

        foreach ($parents as $model) {
            $total = CountModelsLoader::extractCount($model, $this->relation);

            $paginator = $first === 0
                ? new ZeroPerPageLengthAwarePaginator($total, $page)
                : new LengthAwarePaginator($model->getRelation($this->relation), $total, $first, $page);

            $model->setRelation($this->relation, $paginator);
        }
    }
}
