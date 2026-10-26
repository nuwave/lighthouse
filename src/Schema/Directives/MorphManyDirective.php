<?php declare(strict_types=1);

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
  Specify the relationship method name in the model class.
  Only necessary if it is named differently from the field in the schema.
  """
  relation: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]

  """
  Allows to resolve the relation as a paginated list.
  """
  type: MorphManyType

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
  Name a custom object type to use for the edges instead of the generated one.
  It needs the fields `node` and `cursor` and may add more fields.
  Only applies when using Relay style "connection" pagination.
  """
  edgeType: String
) on FIELD_DEFINITION

"""
Options for the `type` argument of `@morphMany`.
"""
enum MorphManyType {
    """
    Offset-based pagination, similar to the Laravel default.
    """
    PAGINATOR

    """
    Offset-based pagination like Laravel "Simple Pagination".
    It does not count the total number of records.
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
