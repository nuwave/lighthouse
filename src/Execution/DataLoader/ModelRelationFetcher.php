<?php

namespace Nuwave\Lighthouse\Execution\DataLoader;

use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Pagination\PaginationArgs;
use ReflectionClass;
use ReflectionMethod;

class ModelRelationFetcher
{
    /**
     * The parent models that relations should be loaded for.
     *
     * @var \Illuminate\Database\Eloquent\Collection<\Illuminate\Database\Eloquent\Model>
     */
    protected $models;

    /**
     * The relations to be loaded. Same format as the `with` method in Eloquent builder.
     *
     * @var array<string, mixed>
     */
    protected $relations;

    /**
     * @param  \Illuminate\Database\Eloquent\Collection  $models  The parent models that relations should be loaded for
     * @param  mixed[]  $relations The relations to be loaded. Same format as the `with` method in Eloquent builder.
     */
    public function __construct(EloquentCollection $models, array $relations)
    {
        $this->models = $models;
        // Parse and set the relations.
        $this->relations = $this->newModelQuery()
            ->with($relations)
            ->getEagerLoads();
    }

    /**
     * Load all relations for the model, but constrain the query to the current page.
     *
     * @return \Illuminate\Database\Eloquent\Collection<\Illuminate\Database\Eloquent\Model>
     */
    public function loadRelationsForPage(PaginationArgs $paginationArgs): EloquentCollection
    {
        // Load the count of relations of models, this will be the `total` argument of `Paginator`.
        // Be aware that this will reload all the models entirely with the count of their relations,
        // which will bring extra DB queries, always prefer querying without pagination if possible.
        $this->reloadModelsWithRelationCount();

        foreach ($this->relations as $name => $constraints) {
            $this->loadRelationForPage($paginationArgs, $name, $constraints);
        }

        return $this->models;
    }

    /**
     * Reload the models to get the `{relation}_count` attributes of models set.
     *
     * @return \Illuminate\Database\Eloquent\Collection<\Illuminate\Database\Eloquent\Model>
     */
    public function reloadModelsWithRelationCount(): EloquentCollection
    {
        $ids = $this->models->modelKeys();

        /** @var \Illuminate\Database\Eloquent\Collection<\Illuminate\Database\Eloquent\Model> $reloadedModels */
        $reloadedModels = $this
            ->newModelQuery()
            ->withCount($this->relations)
            ->whereKey($ids)
            ->get()
            ->filter(function (Model $model) use ($ids): bool {
                // We might have gotten some models that we did not have before
                // so we filter them out
                return in_array(
                    $model->getKey(),
                    $ids,
                    true
                );
            });

        return $this->models = $reloadedModels;
    }

    /**
     * Load only one page of relations of all the models.
     *
     * The relation will be converted to a `Paginator` instance.
     */
    protected function loadRelationForPage(PaginationArgs $paginationArgs, string $relationName, Closure $relationConstraints): void
    {
        $relations = $this
            ->buildRelationsFromModels($relationName, $relationConstraints)
            ->map(
                function (Relation $relation) use ($paginationArgs) {
                    return $relation->forPage($paginationArgs->page, $paginationArgs->first);
                }
            );

        /** @var \Illuminate\Database\Eloquent\Collection $relationModels */
        $relationModels = $this
            ->unionAllRelationQueries($relations)
            ->get();

        $this->hydratePivotRelation($relationName, $relationModels);

        $this->loadDefaultWith($relationModels);

        $this->associateRelationModels($relationName, $relationModels);

        $this->convertRelationToPaginator($paginationArgs, $relationName);
    }

    /**
     * Return a fresh instance of a query builder for the underlying model.
     */
    protected function newModelQuery(): EloquentBuilder
    {
        /** @var \Illuminate\Database\Eloquent\Model $anyModelInstance */
        $anyModelInstance = $this->models->first();

        /** @var \Illuminate\Database\Eloquent\Builder $newModelQuery */
        $newModelQuery = $anyModelInstance->newModelQuery();

        return $newModelQuery;
    }

    /**
     * Get queries to fetch relationships.
     *
     * @return \Illuminate\Support\Collection<\Illuminate\Database\Eloquent\Relations\Relation>
     */
    protected function buildRelationsFromModels(string $relationName, Closure $relationConstraints): Collection
    {
        return $this->models->toBase()->map(
            function (Model $model) use ($relationName, $relationConstraints): Relation {
                $relation = $this->relationInstance($relationName);

                $relation->addEagerConstraints([$model]);

                // Call the constraints
                $relationConstraints($relation, $model);

                if (method_exists($relation, 'shouldSelect')) {
                    $shouldSelect = new ReflectionMethod(get_class($relation), 'shouldSelect');
                    $shouldSelect->setAccessible(true);
                    $select = $shouldSelect->invoke($relation, ['*']);

                    $relation->addSelect($select);
                } elseif (method_exists($relation, 'getSelectColumns')) {
                    $getSelectColumns = new ReflectionMethod(get_class($relation), 'getSelectColumns');
                    $getSelectColumns->setAccessible(true);
                    $select = $getSelectColumns->invoke($relation, ['*']);

                    $relation->addSelect($select);
                }

                $relation->initRelation([$model], $relationName);

                return $relation;
            }
        );
    }

    /**
     * Load default eager loads.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<\Illuminate\Database\Eloquent\Model>  $collection
     * @return $this
     */
    protected function loadDefaultWith(EloquentCollection $collection): self
    {
        $model = $collection->first();
        if ($model === null) {
            return $this;
        }

        $reflection = new ReflectionClass($model);
        $withProperty = $reflection->getProperty('with');
        $withProperty->setAccessible(true);

        $with = array_filter((array) $withProperty->getValue($model), function ($relation) use ($model): bool {
            return ! $model->relationLoaded($relation);
        });

        if (! empty($with)) {
            $collection->load($with);
        }

        return $this;
    }

    /**
     * This is the name that Eloquent gives to the attribute that contains the count.
     *
     * @see \Illuminate\Database\Eloquent\Concerns\QueriesRelationships->withCount()
     */
    protected function relationCountName(string $relationName): string
    {
        return Str::snake("{$relationName}_count");
    }

    /**
     * Merge all the relation queries into a single query with UNION ALL.
     *
     * @param  \Illuminate\Support\Collection<\Illuminate\Database\Eloquent\Relations\Relation>  $relations
     */
    protected function unionAllRelationQueries(Collection $relations): EloquentBuilder
    {
        // We have to make sure to use ->getQuery() in order to respect
        // model scopes, such as soft deletes
        return $relations
            ->reduce(
                function (EloquentBuilder $builder, Relation $relation): EloquentBuilder {
                    // @phpstan-ignore-next-line Laravel is not that strictly typed
                    return $builder->unionAll(
                        $relation->getQuery()
                    );
                },
                // Use the first query as the initial starting point
                $relations->shift()->getQuery()
            );
    }

    /**
     * @return $this
     */
    protected function convertRelationToPaginator(PaginationArgs $paginationArgs, string $relationName): self
    {
        $this->models->each(function (Model $model) use ($paginationArgs, $relationName): void {
            $total = $model->getAttribute(
                $this->relationCountName($relationName)
            );

            $paginator = app()->makeWith(
                LengthAwarePaginator::class,
                [
                    'items' => $model->getRelation($relationName),
                    'total' => $total,
                    'perPage' => $paginationArgs->first,
                    'currentPage' => $paginationArgs->page,
                    'options' => [],
                ]
            );

            $model->setRelation($relationName, $paginator);
        });

        return $this;
    }

    /**
     * Associate the collection of all fetched relationModels back with their parents.
     *
     * @return $this
     */
    protected function associateRelationModels(string $relationName, EloquentCollection $relationModels): self
    {
        $relation = $this->relationInstance($relationName);

        $relation->match(
            $this->models->all(),
            $relationModels,
            $relationName
        );

        return $this;
    }

    /**
     * Ensure the pivot relation is hydrated too, if it exists.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<\Illuminate\Database\Eloquent\Model>  $relationModels
     * @return $this
     */
    protected function hydratePivotRelation(string $relationName, EloquentCollection $relationModels): self
    {
        $relation = $this->relationInstance($relationName);

        if ($relationModels->isNotEmpty() && method_exists($relation, 'hydratePivotRelation')) {
            $hydrationMethod = new ReflectionMethod(get_class($relation), 'hydratePivotRelation');
            $hydrationMethod->setAccessible(true);
            $hydrationMethod->invoke($relation, $relationModels->all());
        }

        return $this;
    }

    /**
     * Use the underlying model to instantiate a relation by name.
     */
    protected function relationInstance(string $relationName): Relation
    {
        return $this
            ->newModelQuery()
            ->getRelation($relationName);
    }
}
