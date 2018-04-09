<?php

namespace Nuwave\Lighthouse\Support\DataLoader;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use ReflectionMethod;

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
    public function eagerLoadCount(Builder $builder, array $models)
    {
        $ids = [];
        $key = $models[0]->getKeyName();
        foreach ($models as $model) {
            $ids[] = $model->{ $key };
        }
        $results = $builder->whereIn($key, $ids)->get();

        $dictionary = [];
        foreach ($results as $result) {
            $dictionary[$result->{ $key }] = $result;
        }

        foreach ($models as $model) {
            if (isset($dictionary[$model->{ $key }])) {
                $model->forceFill($dictionary[$model->{ $key }]->toArray());
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
    public function eagerLoadRelations(Builder $builder, array $models, $perPage = null, $page = null)
    {
        foreach ($builder->getEagerLoads() as $name => $constraints) {
            if (false === strpos($name, '.')) {
                $paginated = ! is_null($perPage) && ! is_null($page);
                $models = $this->loadRelation($builder, $constraints, $models, [
                    'name' => $name,
                    'perPage' => $perPage,
                    'page' => $page,
                    'paginated' => $paginated,
                ]);
            }
        }

        return $models;
    }

    /**
     * Eagerly load the relationship on a set of models.
     *
     * @param Builder $builder
     * @param Closure $constraints
     * @param array   $models
     * @param array   $options
     *
     * @return array
     */
    protected function loadRelation(Builder $builder, Closure $constraints, array $models, array $options)
    {
        $relation = $builder->getRelation($options['name']);
        $queries = $this->getQueries($builder, $models, $options['name'], $constraints);
        $related = $queries->first()->getModel();

        $bindings = $queries->map(function ($query) use ($options) {
            return $query->when($options['paginated'], function ($q) use ($options) {
                $q->forPage($options['page'], $options['perPage']);
            })->getBindings();
        })->collapse()->toArray();

        $sql = $queries->map(function ($query) use ($options) {
            return $query->when($options['paginated'], function ($q) use ($options) {
                $q->forPage($options['page'], $options['perPage']);
            });
        })->map(function ($query) {
            return '('.$query->toSql().')';
        })->implode(' UNION ALL ');

        $table = $related->getTable();
        $results = \DB::select("SELECT `{$table}`.* FROM ({$sql}) AS `{$table}`", $bindings);
        $hydrated = $this->hydrate($related, $relation, $results);
        $matched = $relation->match($models, $related->newCollection($hydrated), $options['name']);

        if ($options['paginated']) {
            foreach ($matched as $model) {
                $total = $model->getAttribute($options['name'].'_count');
                $paginator = app()->makeWith(LengthAwarePaginator::class, [
                    'items' => $model->getRelation($options['name']),
                    'total' => $total,
                    'perPage' => $options['perPage'],
                    'currentPage' => $options['page'],
                    'options' => [],
                ]);

                $model->setRelation($options['name'], $paginator);
            }
        }

        return $matched;
    }

    /**
     * Get queries to fetch relationships.
     *
     * @param Builder $builder
     * @param array   $models
     * @param string  $name
     * @param Closure $constraints
     *
     * @return array
     */
    protected function getQueries(Builder $builder, array $models, $name, Closure $constraints)
    {
        return collect($models)->map(function ($model) use ($builder, $name, $constraints) {
            $relation = $builder->getRelation($name);

            $relation->addEagerConstraints([$model]);

            call_user_func_array($constraints, [$relation, $model]);

            if (method_exists($relation, 'shouldSelect')) {
                $r = new ReflectionMethod(get_class($relation), 'shouldSelect');
                $r->setAccessible(true);
                $select = $r->invoke($relation, ['*']);
                $relation->addSelect($select);
            } elseif (method_exists($relation, 'getSelectColumns')) {
                $r = new ReflectionMethod(get_class($relation), 'getSelectColumns');
                $r->setAccessible(true);
                $select = $r->invoke($relation, ['*']);
                $relation->addSelect($select);
            }

            $relation->initRelation([$model], $name);

            return $relation;
        });
    }

    /**
     * Hydrate related models.
     *
     * @param Model    $related
     * @param Relation $relation
     * @param array    $results
     *
     * @return array
     */
    protected function hydrate(Model $related, Relation $relation, array $results)
    {
        $models = $related->hydrate($results, $related->getConnectionName())->all();

        if (count($models) > 0 && method_exists($relation, 'hydratePivotRelation')) {
            $r = new ReflectionMethod(get_class($relation), 'hydratePivotRelation');
            $r->setAccessible(true);
            $r->invoke($relation, $models);
        }

        return $models;
    }
}
