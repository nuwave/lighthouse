<?php

namespace Nuwave\Lighthouse\Schema\Directives;

class UnionDirective extends BaseDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Use a custom function to determine the concrete type of unions.
"""
directive @union(
  """
  Reference a function that returns the implementing Object Type.
  Consists of two parts: a class name and a method name, seperated by an `@` symbol.
  If you pass only a class name, the method name defaults to `__invoke`.
  """
  resolveType: String!
) on UNION
GRAPHQL;
    }
}
