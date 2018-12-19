<?php

namespace Nuwave\Lighthouse\Execution;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Database\Query\Builder;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\ArgFilterDirective;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

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
     * Get the single instance of the query filter for a field.
     *
     * @param FieldValue $value
     *
     * @return static
     */
    public static function getInstance(FieldValue $value): self
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
     * Check if the ResolveInfo contains a QueryFilter instance and apply it to the query if given.
     *
     * @param Builder|EloquentBuilder $query
     * @param array                   $args
     * @param string[]                $scopes
     * @param ResolveInfo             $resolveInfo
     *
     * @return Builder|EloquentBuilder
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
     * Apply all registered filters to the query.
     *
     * @param Builder|EloquentBuilder $query
     * @param array                   $args
     *
     * @return Builder|EloquentBuilder
     */
    public function filter($query, array $args = [])
    {
        $valuesGroupedByFilterKey = [];

        /**
         * @var string $key
         * @var mixed  $value
         */
        foreach ($args as $key => $value) {
            /**
             * @var string   $filterKey
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

                $query = $argFilterDirective->applyFilter($query, $columnName, $value);
            }
        }

        /**
         * @var string $filterKey
         * @var array  $values
         */
        foreach ($valuesGroupedByFilterKey as $filterKey => $values) {
            $columnName = Str::before($filterKey, '.');

            if ($values) {
                $argFilterDirective = $this->multiArgumentFilters[$filterKey];

                $query = $argFilterDirective->applyFilter($query, $columnName, $values);
            }
        }

        return $query;
    }

    /**
     * @param string             $argumentName
     * @param string             $columnName
     * @param ArgFilterDirective $argFilterDirective
     *
     * @return $this
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
