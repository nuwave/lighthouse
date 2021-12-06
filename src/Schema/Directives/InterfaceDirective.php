<?php

namespace Nuwave\Lighthouse\Schema\Directives;

class InterfaceDirective extends BaseDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Use a custom resolver to determine the concrete type of an interface.
"""
directive @interface(
  """
  Reference to a custom type-resolver function.
  Consists of two parts: a class name and a method name, seperated by an `@` symbol.
  If you pass only a class name, the method name defaults to `__invoke`.
  """
  resolveType: String!
) on INTERFACE
GRAPHQL;
    }
}
