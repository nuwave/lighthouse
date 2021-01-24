<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Laravel\Scout\Builder as ScoutBuilder;

class SearchDirective extends BaseDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Perform a full-text search by the given input value.
"""
directive @search(
  """
  Specify a custom index to use for search.
  """
  within: String
) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
GRAPHQL;
    }

    public function search(ScoutBuilder $builder): void
    {
        $within = $this->directiveArgValue('within');
        if (is_string($within)) {
            $builder->within($within);
        }
    }
}
