<?php

namespace Nuwave\Lighthouse\Support\DataLoader;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class ModelRelationLoader
{
    /**
     * @var EloquentCollection
     */
    protected $models;

    /**
     * @var array
     */
    protected $relations;

    /**
     * @param mixed $models    The models for the given relations to be attached.
     * @param array $relations The relations to be loaded. Same format as the `with` method in Eloquent builder.
     */
    public function __construct($models, array $relations)
    {
        $this->setModels($models)->setRelations($relations);
    }

    /**
     * Set the relations to be loaded.
     *
     * @param array $relations
     *
     * @return static
     */
    public function setRelations(array $relations): self
    {
        // Parse and set the relations.
        $this->relations = $this->newModelQuery()->with($relations)->getEagerLoads();

        return $this;
    }

    /**
     * @return EloquentBuilder
     */
    protected function newModelQuery(): EloquentBuilder
    {
        return $this->models()->first()->newModelQuery();
    }

    /**
     * @return EloquentCollection
     */
    public function models(): EloquentCollection
    {
        return $this->models;
    }

    /**
     * @param mixed $models
     *
     * @return static
     */
    public function setModels($models): self
    {
        $this->models = $models instanceof EloquentCollection ? $models : new EloquentCollection($models);

        return $this;
    }

    /**
     * Load all the relations of all the models.
     *
     * @return static
     */
    public function loadRelations(): self
    {
        $this->models->load($this->relations);

        return $this;
    }

    /**
     * @param int $perPage
     * @param int $page
     *
     * @return ModelRelationLoader
     * @throws \Exception
     */
    public function loadRelationsForPage(int $perPage, int $page = 1): self
    {
        foreach ($this->relations as $name => $constraints) {
            $this->loadRelationForPage($perPage, $page, $name, $constraints);
        }

        return $this;
    }

    /**
     * Load only one page of relations of all the models.
     * The relation will be converted to a `Paginator` instance.
     *
     * @param int $perPage
     * @param int $page
     *
     * @param string $relationName
     * @param \Closure $relationConstraints
     *
     * @return static
     * @throws \Exception
     */
    public function loadRelationForPage(int $perPage, int $page = 1, string $relationName, \Closure $relationConstraints): self
    {
        // Load the count of relations of models, this will be the `total` argument of `Paginator`.
        // Be careful that this will reload all the models entirely with the count of their relations,
        // which will bring extra DB queries, always prefer querying without pagination if possible.
        $this->reloadModelsWithRelationCount();

        $relations = $this->buildRelationsFromModels($relationName, $relationConstraints)->map(
            function (Relation $relation) use ($perPage, $page) {
                return $relation->forPage($page, $perPage);
            }
        );

        /** @var EloquentCollection $relationModels */
        $relationModels = $this->unionAllRelationQueries($relations)->get();

        $this->hydratePivotRelation($relationName, $relationModels);

        $this->loadDefaultWith($relationModels);

        $this->associateRelationModels($relationName, $relationModels);

        $this->convertRelationToPaginator($perPage, $page, $relationName);

        return $this;
    }

    /**
     * Reload the models to get the `{relation}_count` attributes of models set.
     * @return static
     */
    public function reloadModelsWithRelationCount(): self
    {
        /** @var EloquentBuilder $query */
        $query = $this->models()->first()->newQuery()->withCount($this->relations);
        $ids = $this->getModelIds();
        $reloadedModels = $query->whereKey($ids)->get();
        $models = $reloadedModels
            ->filter(function (Model $model) use ($ids) {
                return \in_array(
                    $model->getKey(),
                    $ids,
                    true
                );
            });

        return $this->setModels($models);
    }

    protected function getModelIds(): array
    {
        return $this->models->map(function (Model $model) {
            return $model->getKey();
        })->all();
    }

    /**
     * Get queries to fetch relationships.
     *
     * @param string $relationName
     * @param \Closure $relationConstraints
     *
     * @return Collection Relation[]
     * @throws \Exception
     */
    protected function buildRelationsFromModels(string $relationName, \Closure $relationConstraints): Collection
    {
        return $this->models->toBase()->map(
            function (Model $model) use ($relationName, $relationConstraints) {
                $relation = $this->newModelQuery()->getRelation($relationName);

                $relation->addEagerConstraints([$model]);

                // Call the constraints
                $relationConstraints($relation, $model);

                if (method_exists($relation, 'shouldSelect')) {
                    $shouldSelect = new ReflectionMethod(\get_class($relation), 'shouldSelect');
                    $shouldSelect->setAccessible(true);
                    $select = $shouldSelect->invoke($relation, ['*']);

                    $relation->addSelect($select);
                } elseif (method_exists($relation, 'getSelectColumns')) {
                    $getSelectColumns = new ReflectionMethod(\get_class($relation), 'getSelectColumns');
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
     * @param EloquentCollection $collection
     *
     * @throws \ReflectionException
     *
     * @return static
     */
    protected function loadDefaultWith(EloquentCollection $collection): self
    {
        if ($collection->isNotEmpty()) {
            $model = $collection->first();
            $reflection = new ReflectionClass($model);
            $withProperty = $reflection->getProperty('with');
            $withProperty->setAccessible(true);

            $with = array_filter((array)$withProperty->getValue($model), function ($relation) use ($model) {
                return ! $model->relationLoaded($relation);
            });

            if ( ! empty($with)) {
                $collection->load($with);
            }
        }

        return $this;
    }

    /**
     * @param string $relationName
     *
     * @return string
     */
    public function getRelationCountName(string $relationName): string
    {
        return Str::snake("{$relationName}_count");
    }

    /**
     * @param string $relationName
     *
     * @return array
     */
    public function getRelationDictionary(string $relationName): array
    {
        return $this->models->mapWithKeys(function (Model $model) use ($relationName) {
            return [$model->getKey() => $model->getRelation($relationName)];
        })->all();
    }

    /**
     * @param Collection $relations
     *
     * @return EloquentBuilder
     */
    protected function unionAllRelationQueries(Collection $relations): EloquentBuilder
    {
        return $relations
            ->reduce(
            // Chain together the unions
                function (EloquentBuilder $builder, Relation $relation) {
                    return $builder->unionAll(
                        $relation->getQuery()
                    );
                },
                // Use the first query as the initial starting point
                $relations->shift()->getQuery()
            );
    }

    /**
     * @param int $perPage
     * @param int $page
     * @param string $relationName
     *
     * @return static
     */
    protected function convertRelationToPaginator(int $perPage, int $page, string $relationName): self
    {
        $this->models->each(function (Model $model) use ($page, $perPage, $relationName) {
            $total = $model->getAttribute(
                $this->getRelationCountName($relationName)
            );

            $paginator = app()->makeWith(
                LengthAwarePaginator::class,
                [
                    'items'       => $model->getRelation($relationName),
                    'total'       => $total,
                    'perPage'     => $perPage,
                    'currentPage' => $page,
                    'options'     => [],
                ]
            );

            $model->setRelation($relationName, $paginator);
        });

        return $this;
    }

    /**
     * Associate the relation models with models.
     *
     * @param string $relationName
     * @param EloquentCollection $relationModels
     *
     * @return static
     */
    protected function associateRelationModels(string $relationName, EloquentCollection $relationModels): self
    {
        $relation = $this->newModelQuery()->getRelation($relationName);

        $relation->match($this->models->all(), $relationModels, $relationName);

        return $this;
    }

    /**
     * Ensure the pivot relation is hydrated too, if it exists
     *
     * @param string $relationName
     * @param $relationModels
     *
     * @throws \ReflectionException
     */
    protected function hydratePivotRelation(string $relationName, EloquentCollection $relationModels): void
    {
        $relation = $this->newModelQuery()->getRelation($relationName);

        if ($relationModels->isNotEmpty() && \method_exists($relation, 'hydratePivotRelation')) {
            $hydrationMethod = new ReflectionMethod(\get_class($relation), 'hydratePivotRelation');
            $hydrationMethod->setAccessible(true);
            $hydrationMethod->invoke($relation, $relationModels->all());
        }
    }

}
