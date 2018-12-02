<?php

namespace Nuwave\Lighthouse\Execution;

use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\ArgFilterDirective;

class QueryFilter
{
    const QUERY_FILTER_KEY = 'query.filter';

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
    protected $argumentFilters = [];

    /**
     * A map from a composite key consisting of the columnName and type of key
     * to a list of argument names associated with it.
     *
     * @var array[]
     */
    protected $argumentFiltersArgNames = [];

    /**
     * Get query filter instance for field.
     *
     * @param FieldValue $value
     *
     * @return self
     */
    public static function getInstance(FieldValue $value): QueryFilter
    {
        $handler = static::QUERY_FILTER_KEY
            .'.'.strtolower($value->getParentName())
            .'.'.strtolower($value->getFieldName());

        // Get an existing instance or register a new one
        return app()->has($handler)
            ? resolve($handler)
            : app()->instance($handler, resolve(static::class));
    }

    /**
     * Build query with filter(s).
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param array                              $args
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public static function build($query, array $args)
    {
        // Remove the query filter argument from the args
        $filterInstance = array_pull($args, static::QUERY_FILTER_KEY);

        return $filterInstance
            ? $filterInstance->filter($query, $args)
            : $query;
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
            foreach ($this->argumentFiltersArgNames as $filterKey => $argNames) {
                // Group together the values if multiple arguments are given the same key
                if (in_array($key, $argNames)) {
                    $valuesGroupedByFilterKey[$filterKey][] = $value;
                }
            }

            // Filters that only take a single argument can be applied directly
            if ($filterInfo = array_get($this->singleArgumentFilters, $key)) {
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
            $columnName = str_before($filterKey, '.');

            if ($values) {
                $argFilterDirective = $this->argumentFilters[$filterKey];

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

            $this->argumentFilters[$filterKey] = $argFilterDirective;
            $this->argumentFiltersArgNames[$filterKey][] = $argumentName;
        } else {
            $this->singleArgumentFilters[$argumentName] = [
                'filter' => $argFilterDirective,
                'columnName' => $columnName,
            ];
        }

        return $this;
    }
}
