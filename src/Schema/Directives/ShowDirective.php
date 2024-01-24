<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

class ShowDirective extends HideDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Includes the annotated element from the schema conditionally.
"""
directive @show(
  """
  Specify which environments may use this field, e.g. ["testing"].
  """
  env: [String!]!
) repeatable on FIELD_DEFINITION
GRAPHQL;
    }

    protected function shouldHide(): bool
    {
        return ! parent::shouldHide();
    }
}
