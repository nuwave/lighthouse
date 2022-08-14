<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use BadMethodCallException;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;

class ScopeDirective extends BaseDirective implements ArgBuilderDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Adds a scope to the query builder.

The scope method will receive the client-given value of the argument as the second parameter.
"""
directive @scope(
  """
  The name of the scope.
  Defaults to the name of the argument.
  """
  name: String
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
GRAPHQL;
    }

    /**
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    public function handleBuilder($builder, $value): object
    {
        $scope = $this->directiveArgValue('name', $this->nodeName());

        try {
            return $builder->{$scope}($value);
            // @phpstan-ignore-next-line PHPStan thinks this exception does not occur - but it does. Magic.
        } catch (BadMethodCallException $exception) {
            throw new DefinitionException(
                $exception->getMessage() . " in @{$this->name()} directive on {$this->nodeName()} argument.",
                $exception->getCode(),
                $exception->getPrevious()
            );
        }
    }
}
