<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

class HasOneThroughDirective extends RelationDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Corresponds to [the Eloquent relationship HasOneThrough](https://laravel.com/docs/eloquent-relationships#has-one-through).
"""
directive @hasOneThrough(
  """
  Specify the relationship method name in the model class,
  if it is named different from the field in the schema.
  """
  relation: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]
) on FIELD_DEFINITION
GRAPHQL;
    }
}
