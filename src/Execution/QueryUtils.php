<?php

namespace Nuwave\Lighthouse\Execution;

class QueryUtils
{
    /**
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query
     * @param array $args
     *
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public static function applyFilters($query, array $args)
    {
        return $query->when(
            isset($args[QueryFilter::QUERY_FILTER_KEY]),
            function ($builder) use ($args) {
                return QueryFilter::build($builder, $args);
            }
        );
    }

    /**
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query
     * @param array $args
     * @param array $scopes
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public static function applyScopes($query, array $args, array $scopes)
    {
        foreach ($scopes as $scope) {
            call_user_func([$query, $scope], $args);
        }

        return $query;
    }
}
