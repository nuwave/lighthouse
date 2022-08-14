<?php

namespace Nuwave\Lighthouse\Execution\ModelsLoader;

use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Nuwave\Lighthouse\Pagination\PaginationArgs;
use Nuwave\Lighthouse\Pagination\ZeroPageLengthAwarePaginator;
use ReflectionClass;
use ReflectionMethod;

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

    public function __construct(string $relation, Closure $decorateBuilder, PaginationArgs $paginationArgs)
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
                    $shouldSelect = new ReflectionMethod(get_class($relation), 'shouldSelect');
                    $shouldSelect->setAccessible(true);
                    $select = $shouldSelect->invoke($relation, ['*']);

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

        return $mergedRelationQuery->get();
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
            $hydrationMethod = new ReflectionMethod($relation, 'hydratePivotRelation');
            $hydrationMethod->setAccessible(true);
            $hydrationMethod->invoke($relation, $relatedModels->all());
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

        $reflection = new ReflectionClass($model);
        $withProperty = $reflection->getProperty('with');
        $withProperty->setAccessible(true);

        /** @var array<int, string> $unloadedWiths */
        $unloadedWiths = array_filter(
            $withProperty->getValue($model),
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
