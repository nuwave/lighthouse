<?php

namespace Nuwave\Lighthouse\Support\DataLoader;

use Closure;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Model;

class QueryBuilder
{
    /**
     * Eager load relationships on collection.
     *
     * @param  Builder $builder
     * @param  array   $models
     * @return array
     */
    public function eagerLoadRelations(Builder $builder, array $models)
    {
        foreach ($builder->getEagerLoads() as $name => $constraints) {
            if (strpos($name, '.') === false) {
                $models = $this->loadRelation($builder, $models, $name, $constraints);
            }
        }

        return $models;
    }

    /**
     * Eagerly load the relationship on a set of models.
     *
     * @param  Builder  $builder
     * @param  array    $models
     * @param  string   $name
     * @param  Closure  $constraints
     * @return array
     */
    protected function loadRelation(Builder $builder, array $models, $name, Closure $constraints)
    {
        $relation = $builder->getRelation($name);
        $queries = $this->getQueries($builder, $models, $name, $constraints);

        $related = $queries->first()->getModel();

        $bindings = $queries->map(function ($query) {
            return $query->getBindings();
        })->collapse()->toArray();

        $sql = $queries->map(function ($query) {
            return '('.$query->toSql().')';
        })->implode(' UNION ALL ');

        $table = $related->getTable();
        $results = \DB::select("SELECT `{$table}`.* FROM ({$sql}) AS `{$table}`", $bindings);
        $hydrated = $this->hydrate($related, $relation, $results);

        return $relation->match($models, $related->newCollection($hydrated), $name);
    }

    /**
     * Get queries to fetch relationships.
     *
     * @param  Builder  $builder
     * @param  array    $models
     * @param  string   $name
     * @param  Closure  $constraints
     * @return array
     */
    protected function getQueries(Builder $builder, array $models, $name, Closure $constraints)
    {
        return Collection::make($models)->map(function ($model) use ($builder, $name, $constraints) {
            $relation = $builder->getRelation($name);

            $relation->addEagerConstraints([$model]);

            call_user_func_array($constraints, [$relation, $model]);

            if (method_exists($relation, 'getSelectColumns')) {
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
     * @param  Model    $related
     * @param  Relation $relation
     * @param  array    $results
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
