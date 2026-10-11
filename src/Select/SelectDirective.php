<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Select;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

class SelectDirective extends BaseDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Select only the database columns that the queried fields need.

On a field, list the columns the field needs to resolve.
On a type, list the columns to select regardless of the queried fields,
e.g. for policies or model events.

Once a type or one of its fields uses @select, every field of the type must use @select,
unless a relation directive such as @hasMany, @belongsTo or @count resolves it.
"""
directive @select(
  """
  Database column names to include in the `SELECT` part of the query.
  """
  columns: [String!]!
) on FIELD_DEFINITION | OBJECT
GRAPHQL;
    }
}
