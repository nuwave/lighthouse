<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;

class MorphToManyDirective extends RelationDirective implements FieldManipulator
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Corresponds to [Eloquent's ManyToMany-Polymorphic-Relationship](https://laravel.com/docs/eloquent-relationships#many-to-many-polymorphic-relations).
"""
directive @morphToMany(
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
  """
  type: MorphToManyType

  """
  Allow clients to query paginated lists without specifying the amount of items.
  Overrules the `pagination.default_count` setting from `lighthouse.php`.
  Setting this to `null` means clients have to explicitly ask for the count.
  """
  defaultCount: Int

  """
  Limit the maximum amount of items that clients can request from paginated lists.
  Overrules the `pagination.max_count` setting from `lighthouse.php`.
  Setting this to `null` means the count is unrestricted.
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
Options for the `type` argument of `@morphToMany`.
"""
enum MorphToManyType {
    """
    Offset-based pagination, similar to the Laravel default.
    """
    PAGINATOR

    """
    Offset-based pagination like the Laravel "Simple Pagination", which does not count the total number of records.
    """
    SIMPLE

    """
    Cursor-based pagination, compatible with the Relay specification.
    """
    CONNECTION
}
GRAPHQL;
    }
}
