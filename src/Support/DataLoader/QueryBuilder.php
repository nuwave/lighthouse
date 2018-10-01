<?php

namespace Nuwave\Lighthouse\Support\DataLoader;

use ReflectionClass;
use ReflectionMethod;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Relations\Relation;

class QueryBuilder
{
    /**
     * Eager load count on collection of models.
     *
     * Thanks to marcus13371337
     * https://github.com/laravel/framework/issues/17845#issuecomment-313701089
     *
     * @param Builder $builder
     * @param array   $models
     *
     * @return array
     */
    public function eagerLoadCount(Builder $builder, array $models): array
    {
        $ids = [];
        $key = $models[0]->getKeyName();
        foreach ($models as $model) {
            $ids[] = $model->{$key};
        }
        
        $results = $builder
            ->whereIn($key, $ids)
            ->get();

        $dictionary = [];
        foreach ($results as $result) {
            $dictionary[$result->{$key}] = $result;
        }

        foreach ($models as $model) {
            if (isset($dictionary[$model->{$key}])) {
                $model->forceFill($dictionary[$model->{$key}]
                    ->toArray());
            }
        }

        return $models;
    }

    /**
     * Eager load relationships on collection.
     *
     * @param Builder $builder
     * @param array   $models
     * @param int     $perPage
     * @param int     $page
     *
     * @return array
     */
    public function eagerLoadRelations(Builder $builder, array $models, int $perPage = null, int $page = null): array
    {
        foreach ($builder->getEagerLoads() as $name => $constraints) {
            if (false === strpos($name, '.')) {
                $paginated = ! is_null($perPage) && ! is_null($page);
                
                $models = $this->loadRelation(
                    $builder,
                    $constraints,
                    $models,
                    [
                        'name' => $name,
                        'perPage' => $perPage,
                        'page' => $page,
                        'paginated' => $paginated,
                    ]
                );
            }
        }

        return $models;
    }

    /**
     * Eagerly load the relationship on a set of models.
     *
     * @param Builder  $builder
     * @param \Closure $constraints
     * @param array    $models
     * @param array    $options
     *
     * @return array
     */
    protected function loadRelation(Builder $builder, \Closure $constraints, array $models, array $options): array
    {
        $relation = $builder->getRelation($options['name']);
        $relationQueries = $this->getRelationQueries($builder, $models, $options['name'], $constraints);

        if($options['paginated']){
            $relationQueries = $relationQueries->map(
                function (Relation $relation) use ($options) {
                    return $relation->forPage($options['page'], $options['perPage']);
                }
            );
        }

        /** @var \Illuminate\Database\Eloquent\Collection $unitedRelations */
        $unitedRelations = $relationQueries
            ->reduce(
                // Chain together the unions
                function (Builder $builder, Relation $relation) {
                    return $builder->unionAll(
                        $relation->getQuery()
                    );
                },
                // Use the first query as the initial starting point
                $relationQueries->shift()->getQuery()
            )
            ->get();

        // Ensure the pivot relation is hydrated too, if it exists
        if ($unitedRelations->isNotEmpty() && method_exists($relation, 'hydratePivotRelation')) {
            $hydrationMethod = new ReflectionMethod(get_class($relation), 'hydratePivotRelation');
            $hydrationMethod->setAccessible(true);
            $hydrationMethod->invoke($relation, $unitedRelations->all());
        }

        $allRelations = $this->loadDefaultWith($unitedRelations);

        $matched = $relation->match($models, $allRelations, $options['name']);

        if ($options['paginated']) {
            foreach ($matched as $model) {
                $total = $model->getAttribute(
                    snake_case($options['name']).'_count'
                );
                
                $paginator = app()->makeWith(
                    LengthAwarePaginator::class,
                    [
                        'items' => $model->getRelation($options['name']),
                        'total' => $total,
                        'perPage' => $options['perPage'],
                        'currentPage' => $options['page'],
                        'options' => [],
                    ]
                );

                $model->setRelation($options['name'], $paginator);
            }
        }

        return $matched;
    }

    /**
     * Get queries to fetch relationships.
     *
     * @param Builder  $builder
     * @param array    $models
     * @param string   $name
     * @param \Closure $constraints
     *
     * @return Collection|Relation[]
     */
    protected function getRelationQueries(Builder $builder, array $models, string $name, \Closure $constraints): Collection
    {
        return collect($models)->map(
            function (Model $model) use ($builder, $name, $constraints) {
                $relation = $builder->getRelation($name);
        
                $relation->addEagerConstraints([$model]);
        
                // Call the constraints
                $constraints($relation, $model);
        
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
        
                $relation->initRelation([$model], $name);
        
                return $relation;
            }
        );
    }

    /**
     * Load default eager loads.
     *
     * @param Collection $collection
     *
     * @throws \ReflectionException
     *
     * @return Collection
     */
    protected function loadDefaultWith(Collection $collection)
    {
        if ($collection->isNotEmpty()) {
            $model = $collection->first();
            $reflection = new ReflectionClass($model);
            $withProperty = $reflection->getProperty('with');
            $withProperty->setAccessible(true);

            $with = array_filter((array) $withProperty->getValue($model), function ($relation) use ($model) {
                return ! $model->relationLoaded($relation);
            });

            if (! empty($with)) {
                $collection->load($with);
            }
        }

        return $collection;
    }
}
