<?php

namespace Nuwave\Lighthouse\Support\DataLoader;

use Closure;
use Illuminate\Database\Eloquent\Builder;

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
        $queries = collect($models)->map(function ($model) use ($builder, $name, $constraints) {
            $relation = $builder->getRelation($name);

            $relation->addEagerConstraints([$model]);

            call_user_func_array($constraints, [$relation, $model]);

            return $relation;
        });

        $bindings = $queries->map(function ($query) {
            return $query->getBindings();
        })->collapse()->toArray();

        $sql = $queries->map(function ($query) {
            return '(' . $query->toSql() . ')';
        })->implode(' UNION ALL ');

        $relatedModel = $queries->first()->getModel();
        $table = $relatedModel->getTable();

        $fetch = \DB::select("SELECT `{$table}`.* FROM ({$sql}) AS `{$table}`", $bindings);
        $results = $relatedModel->hydrate($fetch, $relatedModel->getConnectionName())->all();
        $relation = $builder->getRelation($name);

        return $relation->match($models, $relatedModel->newCollection($results), $name);
    }
}
