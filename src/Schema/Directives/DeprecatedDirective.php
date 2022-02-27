<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\Directive;

class DeprecatedDirective implements Directive
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Marks an element of a GraphQL schema as no longer supported.
"""
directive @deprecated(
  """
  Explains why this element was deprecated, usually also including a
  suggestion for how to access supported similar data. Formatted
  in [Markdown](https://daringfireball.net/projects/markdown).
  """
  reason: String = "No longer supported"
) on FIELD_DEFINITION | ENUM_VALUE
GRAPHQL;
    }
}
