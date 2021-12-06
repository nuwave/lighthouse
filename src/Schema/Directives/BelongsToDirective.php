<?php

namespace Nuwave\Lighthouse\Schema\Directives;

class BelongsToDirective extends RelationDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Resolves a field through the Eloquent `BelongsTo` relationship.
"""
directive @belongsTo(
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
