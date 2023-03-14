<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Scout;

use GraphQL\Utils\Utils;
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
        if ($within !== null) {
            if (! is_string($within)) {
                $notString = Utils::printSafeJson($within);
                throw new DefinitionException("Expected the value of the `within` argument of @{$this->name()} on {$this->nodeName()} to be a string, got: {$notString}.");
            }

            $builder->within($within);
        }
    }
}
