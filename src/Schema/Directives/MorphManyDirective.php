<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;

class MorphManyDirective extends RelationDirective implements FieldManipulator
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Corresponds to [Eloquent's MorphMany-Relationship](https://laravel.com/docs/eloquent-relationships#one-to-many-polymorphic-relations).
"""
directive @morphMany(
  """
  Specify the relationship method name in the model class,
  if it is named different from the field in the schema.
  """
  relation: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]

  """
  Allows to resolve the relation as a paginated list.
  Allowed values: `paginator`, `connection`.
  """
  type: String

  """
  Allow clients to query paginated lists without specifying the amount of items.
  Overrules the `pagination.default_count` setting from `lighthouse.php`.
  """
  defaultCount: Int

  """
  Limit the maximum amount of items that clients can request from paginated lists.
  Overrules the `pagination.max_count` setting from `lighthouse.php`.
  """
  maxCount: Int

  """
  Specify a custom type that implements the Edge interface
  to extend edge object.
  Only applies when using Relay style "connection" pagination.
  """
  edgeType: String
) on FIELD_DEFINITION
GRAPHQL;
    }
}
