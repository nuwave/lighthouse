<?php

namespace Nuwave\Lighthouse\Execution;

use Illuminate\Support\Arr;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;

class QueryFilter
{
    /**
     * A map from argument names to associated query builder directives.
     *
     * @var ArgBuilderDirective[]
     */
    protected $builders = [];

    /**
     * Get the single instance of the query filter for a field.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $value
     * @return static
     */
    public static function getInstance(FieldValue $value): self
    {
        $handler = 'query.filter'
            .'.'.strtolower($value->getParentName())
            .'.'.strtolower($value->getFieldName());

        // Get an existing instance or register a new one
        return app()->bound($handler)
            ? app($handler)
            : app()->instance($handler, app(static::class));
    }

    /**
     * Check if the ResolveInfo contains a QueryFilter instance and apply it to the query if given.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     * @param  array  $args
     * @param  string[]  $scopes
     * @param  \GraphQL\Type\Definition\ResolveInfo  $resolveInfo
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public static function apply($query, array $args, array $scopes, ResolveInfo $resolveInfo)
    {
        /** @var \Nuwave\Lighthouse\Execution\QueryFilter $queryFilter */
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
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed[]  $args
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function filter($query, array $args = [])
    {
        foreach ($args as $key => $value) {
            // Filters that only take a single argument can be applied directly
            if ($builderDirective = Arr::get($this->builders, $key)) {
                $query = $builderDirective->handleBuilder($query, $value);
            }
        }

        return $query;
    }

    /**
     * Add a query builder directive keyed by the argument name.
     *
     * @param  string  $argumentName
     * @param  \Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective  $argBuilderDirective
     * @return $this
     */
    public function addBuilder(string $argumentName, ArgBuilderDirective $argBuilderDirective): self
    {
        $this->builders[$argumentName] = $argBuilderDirective;

        return $this;
    }
}
