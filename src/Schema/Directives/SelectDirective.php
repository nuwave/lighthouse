<?php

namespace Nuwave\Lighthouse\Schema\Directives;

class SelectDirective extends BaseDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Specify the SQL column dependencies of this field.
"""
directive @select(
  """
  SQL column names to include in the `SELECT` part of the query.
  """
  columns: [String!]!
) on FIELD_DEFINITION
GRAPHQL;
    }
}
