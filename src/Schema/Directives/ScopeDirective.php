<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;

/**
 * This directive adds a scope to the builder and supplies the argument value
 * as the argument to the scope.
 */
class ScopeDirective extends BaseDirective implements ArgBuilderDirective, DefinedDirective
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'scope';
    }

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'SDL'
"""
Adds a scope to the builder.
"""
directive @scope(
  """
  The name of the scope.
  """
  name: String
) on ARGUMENT_DEFINITION
SDL;
    }

    /**
     * Add additional constraints to the builder based on the given argument value.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $builder
     * @param  mixed $value
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function handleBuilder($builder, $value)
    {
        $scope = $this->directiveArgValue('name');
        return $builder->{$scope}($value);
    }
}
