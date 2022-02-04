<?php

namespace Nuwave\Lighthouse\Scout;

use Laravel\Scout\Builder as ScoutBuilder;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

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
        if (null !== $within) {
            if (! is_string($within)) {
                throw new DefinitionException(
                    "Expected the value of the `within` argument of @{$this->name()} on {$this->nodeName()} to be a string, got: " . \Safe\json_encode($within)
                );
            }

            $builder->within($within);
        }
    }
}
