<?php

namespace Nuwave\Lighthouse\Execution;


use Nuwave\Lighthouse\Support\Database\QueryFilter;

class QueryUtils
{
    public static function applyFilters($query, array $args)
    {
        return $query->when(isset($args['query.filter']), function ($builder) use ($args) {
            return QueryFilter::build($builder, $args);
        });
    }

    public static function applyScopes($query, array $args, array $scopes)
    {
        foreach ($scopes as $scope) {
            call_user_func_array([$query, $scope], [$args]);
        }

        return $query;
    }
}
