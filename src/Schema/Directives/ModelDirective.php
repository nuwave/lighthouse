<?php

namespace Nuwave\Lighthouse\Schema\Directives;

class ModelDirective extends BaseDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Map a model class to an object type.

This can be used when the name of the model differs from the name of the type.
"""
directive @model(
  """
  The class name of the corresponding model.
  """
  class: String!
) on OBJECT
GRAPHQL;
    }
}
