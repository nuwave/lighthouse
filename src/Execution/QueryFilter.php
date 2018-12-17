<?php

namespace Nuwave\Lighthouse\Execution;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\ArgFilterDirective;

class QueryFilter
{
    /**
     * Filters that only require a single argument.
     *
     * Are keyed by the arguments name and contain the columnName and the ArgFilterDirective.
     *
     * @var array[]
     */
    protected $singleArgumentFilters = [];

    /**
     * A map from a composite key consisting of the columnName and type of key
     * to the Closure that will resolve the key.
     *
     * @var ArgFilterDirective[]
     */
    protected $multiArgumentFilters = [];

    /**
     * A map from a composite key consisting of the columnName and type of key
     * to a list of argument names associated with it.
     *
     * @var array[]
     */
    protected $multiArgumentFiltersArgNames = [];

    /**
     * Get query filter instance for field.
     *
     * @param FieldValue $value
     *
     * @return self
     */
    public static function getInstance(FieldValue $value): QueryFilter
    {
        $handler = 'query.filter'
            .'.'.strtolower($value->getParentName())
            .'.'.strtolower($value->getFieldName());

        // Get an existing instance or register a new one
        return app()->has($handler)
            ? resolve($handler)
            : app()->instance($handler, resolve(static::class));
    }

    /**
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query
     * @param array                                                                    $args
     * @param array                                                                    $scopes
     * @param ResolveInfo                                                              $resolveInfo
     *
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public static function apply($query, array $args, array $scopes, ResolveInfo $resolveInfo)
    {
        /** @var QueryFilter $queryFilter */
        if ($queryFilter = $resolveInfo->queryFilter ?? false) {
            $query = $queryFilter->filter($query, $args);
        }

        foreach ($scopes as $scope) {
            call_user_func([$query, $scope], $args);
        }

        return $query;
    }

    /**
     * Run query through filter.
     *
     * @param \Illuminate\Database\Query\Builder $builder
     * @param array                              $args
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function filter($builder, array $args = [])
    {
        $valuesGroupedByFilterKey = [];

        /**
         * @var string
         * @var mixed  $value
         */
        foreach ($args as $key => $value) {
            /**
             * @var string
             * @var string[] $argNames
             */
            foreach ($this->multiArgumentFiltersArgNames as $filterKey => $argNames) {
                // Group together the values if multiple arguments are given the same key
                if (in_array($key, $argNames)) {
                    $valuesGroupedByFilterKey[$filterKey][] = $value;
                }
            }

            // Filters that only take a single argument can be applied directly
            if ($filterInfo = Arr::get($this->singleArgumentFilters, $key)) {
                /** @var ArgFilterDirective $argFilterDirective */
                $argFilterDirective = $filterInfo['filter'];
                $columnName = $filterInfo['columnName'];

                $builder = $argFilterDirective->applyFilter($builder, $columnName, $value);
            }
        }

        /**
         * @var string
         * @var array  $values
         */
        foreach ($valuesGroupedByFilterKey as $filterKey => $values) {
            $columnName = Str::before($filterKey, '.');

            if ($values) {
                $argFilterDirective = $this->multiArgumentFilters[$filterKey];

                $builder = $argFilterDirective->applyFilter($builder, $columnName, $values);
            }
        }

        return $builder;
    }

    /**
     * @param string             $argumentName
     * @param string             $columnName
     * @param ArgFilterDirective $argFilterDirective
     *
     * @return QueryFilter
     */
    public function addArgumentFilter(string $argumentName, string $columnName, ArgFilterDirective $argFilterDirective): QueryFilter
    {
        if ($argFilterDirective->combinesMultipleArguments()) {
            $filterKey = "{$columnName}.{$argFilterDirective->name()}";

            $this->multiArgumentFilters[$filterKey] = $argFilterDirective;
            $this->multiArgumentFiltersArgNames[$filterKey][] = $argumentName;
        } else {
            $this->singleArgumentFilters[$argumentName] = [
                'filter' => $argFilterDirective,
                'columnName' => $columnName,
            ];
        }

        return $this;
    }
}
