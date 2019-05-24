<?php

namespace Nuwave\Lighthouse\Execution;

use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;

class Builder
{
    /**
     * A map from argument names to associated query builder directives.
     *
     * @var ArgBuilderDirective[]
     */
    protected $builderDirectives = [];

    /**
     * Scopes to be applied to the query builder.
     *
     * @var string[]
     */
    protected $scopes = [];

    /**
     * @var \Nuwave\Lighthouse\Execution\QueryFilter
     * @deprecated once QueryFilter is removed
     */
    protected $queryFilter;

    /**
     * Apply the bound QueryBuilderDirectives to the given builder.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder
     * @param  mixed[]  $args
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function apply($builder, array $args)
    {
        /*
         * Call the queryFilter for backwards compatibility
         * @deprecated
         */
        $this->queryFilter->filter($builder, $args);

        foreach ($args as $key => $value) {
            /** @var \Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective $builderDirective */
            if ($builderDirective = Arr::get($this->builderDirectives, $key)) {
                $builder = $builderDirective->handleBuilder($builder, $value);
            }
        }

        foreach ($this->scopes as $scope) {
            call_user_func([$builder, $scope], $args);
        }

        return $builder;
    }

    /**
     * Add scopes that are then called upon the query with the field arguments.
     *
     * @param  string[]  $scopes
     * @return $this
     */
    public function addScopes(array $scopes): self
    {
        $this->scopes = array_merge($this->scopes, $scopes);

        return $this;
    }

    /**
     * Add a query builder directive keyed by the argument name.
     *
     * @param  string  $argumentName
     * @param  \Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective  $argBuilderDirective
     * @return $this
     */
    public function addBuilderDirective(string $argumentName, ArgBuilderDirective $argBuilderDirective): self
    {
        $this->builderDirectives[$argumentName] = $argBuilderDirective;

        return $this;
    }

    /**
     * Keep a reference to the QueryFilter for backwards compatibility.
     *
     * @deprecated
     * @param  \Nuwave\Lighthouse\Execution\QueryFilter  $queryFilter
     * @return $this
     */
    public function setQueryFilter(QueryFilter $queryFilter): self
    {
        $this->queryFilter = $queryFilter;

        return $this;
    }
}
