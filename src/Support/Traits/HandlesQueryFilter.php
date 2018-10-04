<?php

namespace Nuwave\Lighthouse\Support\Traits;

use Nuwave\Lighthouse\Execution\QueryFilter;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;

trait HandlesQueryFilter
{
    /**
     * Inject query filter into field.
     *
     * @param ArgumentValue $argument
     * @param \Closure $filter
     *
     * The filter Closure receives three positional arguments:
     *
     * mixed $builder An instance of the query builder
     * string $columnName
     * mixed $value An array of values
     *
     * @return ArgumentValue
     */
    protected function injectFilter(ArgumentValue &$argument, \Closure $filter): ArgumentValue
    {
        $parentField = $argument->getParentField();
        $query = QueryFilter::getInstance(
            $parentField
        );
    
        $argumentName = $this->definitionNode->name->value;
        $query->addSingleArgumentFilter(
            $argumentName,
            $filter,
            $this->directiveArgValue('key', $argumentName)
        );

        $parentField->injectArg(
            QueryFilter::QUERY_FILTER_KEY,
            $query
        );

        return $argument;
    }
    
    /**
     * Inject a query filter that takes an array of values into the field.
     *
     * @param ArgumentValue $argument
     * @param \Closure $filter
     *
     * The filter Closure receives three positional arguments:
     *
     * mixed $builder An instance of the query builder
     * string $columnName
     * mixed $value A single value
     *
     * @param string $filterType You have to specify a filter type so that the filters can be matched together
     *
     * @return ArgumentValue
     */
    protected function injectMultiArgumentFilter(ArgumentValue &$argument, \Closure $filter, string $filterType): ArgumentValue
    {
        $parentField = $argument->getParentField();
        $query = QueryFilter::getInstance(
            $parentField
        );
    
        $argumentName = $this->definitionNode->name->value;
        $query->addMultiArgumentFilter(
            $argumentName,
            $filter,
            $this->directiveArgValue('key', $argumentName),
            $filterType
        );

        $parentField->injectArg(
            QueryFilter::QUERY_FILTER_KEY,
            $query
        );

        return $argument;
    }
}
