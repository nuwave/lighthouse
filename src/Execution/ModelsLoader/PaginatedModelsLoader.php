<?php

namespace Nuwave\Lighthouse\Execution\ModelsLoader;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Nuwave\Lighthouse\Pagination\PaginationArgs;
use Nuwave\Lighthouse\Pagination\ZeroPageLengthAwarePaginator;
use Nuwave\Lighthouse\Support\Utils;

class PaginatedModelsLoader implements ModelsLoader
{
    /**
     * @var string
     */
    protected $relation;

    /**
     * @var \Closure
     */
    protected $decorateBuilder;

    /**
     * @var \Nuwave\Lighthouse\Pagination\PaginationArgs
     */
    protected $paginationArgs;

    public function __construct(string $relation, \Closure $decorateBuilder, PaginationArgs $paginationArgs)
    {
        $this->relation = $relation;
        $this->decorateBuilder = $decorateBuilder;
        $this->paginationArgs = $paginationArgs;
    }

    public function load(EloquentCollection $parents): void
    {
        CountModelsLoader::loadCount($parents, [$this->relation => $this->decorateBuilder]);

        $relatedModels = $this->loadRelatedModels($parents);

        $relation = $this->relationInstance($parents);

        $this->hydratePivotRelation($relation, $relatedModels);
        $this->loadDefaultWith($relatedModels);
        $this->associateRelationModels($parents, $relatedModels);
        $this->convertRelationToPaginator($parents);
    }

    public function extract(Model $model)
    {
        return $model->getRelation($this->relation);
    }

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

                    // @phpstan-ignore-next-line Builder mixin is not understood
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
            static function (EloquentBuilder $builder, Relation $relation): EloquentBuilder {
                return $builder->unionAll(
                    // @phpstan-ignore-next-line Laravel can deal with an EloquentBuilder just fine
                    $relation->getQuery()
                );
            },
            $firstRelation->getQuery()
        );

        $relatedModels = $mergedRelationQuery->get();
        assert($relatedModels instanceof EloquentCollection);

        return $relatedModels->unique(function (Model $relatedModel): string {
            // Compare all attributes because there might not be a unique primary key
            // or there could be differing pivot attributes.
            return $relatedModel->toJson();
        });
    }

    /**
     * Use the underlying model to instantiate a relation by name.
     */
    protected function relationInstance(EloquentCollection $parents): Relation
    {
        return $this
            ->newModelQuery($parents)
            ->getRelation($this->relation);
    }

    /**
     * Return a fresh instance of a query builder for the underlying model.
     */
    protected function newModelQuery(EloquentCollection $parents): EloquentBuilder
    {
        $anyModelInstance = $parents->first();
        assert($anyModelInstance instanceof Model);

        $newModelQuery = $anyModelInstance->newModelQuery();
        assert($newModelQuery instanceof EloquentBuilder);

        return $newModelQuery;
    }

    /**
     * Ensure the pivot relation is hydrated too, if it exists.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<\Illuminate\Database\Eloquent\Model>  $relatedModels
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
     * @param  \Illuminate\Database\Eloquent\Collection<\Illuminate\Database\Eloquent\Model>  $models
     */
    protected function loadDefaultWith(EloquentCollection $models): void
    {
        $model = $models->first();
        if (null === $model) {
            return;
        }
        assert($model instanceof Model);

        $unloadedWiths = array_filter(
            Utils::accessProtected($model, 'with'),
            static function (string $relation) use ($model): bool {
                return ! $model->relationLoaded($relation);
            }
        );

        if (count($unloadedWiths) > 0) {
            $models->load($unloadedWiths);
        }
    }

    /**
     * Associate the collection of all fetched relationModels back with their parents.
     */
    protected function associateRelationModels(EloquentCollection $parents, EloquentCollection $relatedModels): void
    {
        $this
            ->relationInstance($parents)
            ->match(
                $parents->all(),
                $relatedModels,
                $this->relation
            );
    }

    protected function convertRelationToPaginator(EloquentCollection $parents): void
    {
        $first = $this->paginationArgs->first;
        $page = $this->paginationArgs->page;

        foreach ($parents as $model) {
            $total = CountModelsLoader::extractCount($model, $this->relation);

            $paginator = 0 === $first
                ? new ZeroPageLengthAwarePaginator($total, $page)
                : new LengthAwarePaginator($model->getRelation($this->relation), $total, $first, $page);

            $model->setRelation($this->relation, $paginator);
        }
    }
}
