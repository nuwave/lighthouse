<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;

class HasManyDirective extends RelationDirective implements FieldManipulator
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Corresponds to [the Eloquent relationship HasMany](https://laravel.com/docs/eloquent-relationships#one-to-many).
"""
directive @hasMany(
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
  type: HasManyType

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

"""
Options for the `type` argument of `@hasMany`.
"""
enum HasManyType {
    """
    Offset-based pagination, similar to the Laravel default.
    """
    PAGINATOR

    """
    Cursor-based pagination, compatible with the Relay specification.
    """
    CONNECTION
}
GRAPHQL;
    }
}
