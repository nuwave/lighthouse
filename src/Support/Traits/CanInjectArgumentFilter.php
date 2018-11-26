<?php

namespace Nuwave\Lighthouse\Support\Traits;

use Nuwave\Lighthouse\Execution\QueryFilter;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;

trait CanInjectArgumentFilter
{
    /**
     * Inject query filter into field.
     *
     * @param string     $argumentName
     * @param FieldValue $parentField
     * @param \Closure   $filter
     *
     * The filter Closure receives three positional arguments:
     *
     * mixed $builder An instance of the query builder
     * string $columnName
     * mixed $value An array of values
     * @param string $columnName
     *
     * @return static
     */
    protected function injectSingleArgumentFilter(
        string $argumentName,
        FieldValue $parentField,
        \Closure $filter,
        string $columnName
    ): self {
        $query = QueryFilter::getInstance($parentField);

        $query->addSingleArgumentFilter(
            $argumentName,
            $filter,
            $columnName
        );

        $parentField->injectArg(
            QueryFilter::QUERY_FILTER_KEY,
            $query
        );

        return $this;
    }

    /**
     * Inject a query filter that takes an array of values into the field.
     *
     * @param string     $argumentName
     * @param FieldValue $parentField
     * @param string     $filterType   You have to specify a filter type so that the filters can be matched together
     * @param \Closure   $filter
     *
     * The filter Closure receives three positional arguments:
     *
     * mixed $builder An instance of the query builder
     * string $columnName
     * mixed $value A single value
     * @param string $columnName
     *
     * @return static
     */
    protected function injectMultiArgumentFilter(
        string $argumentName,
        FieldValue $parentField,
        string $filterType,
        \Closure $filter,
        string $columnName
    ): self {
        $query = QueryFilter::getInstance($parentField);

        $query->addMultiArgumentFilter(
            $argumentName,
            $filter,
            $columnName,
            $filterType
        );

        $parentField->injectArg(
            QueryFilter::QUERY_FILTER_KEY,
            $query
        );

        return $this;
    }
}
