<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Laravel\Scout\Builder as ScoutBuilder;
use Nuwave\Lighthouse\Scout\ScoutBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;

class EqDirective extends BaseDirective implements ArgBuilderDirective, ScoutBuilderDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Use the client given value to add an equal conditional to a database query.
"""
directive @eq(
  """
  Specify the database column to compare.
  Only required if database column has a different name than the attribute in your schema.
  """
  key: String
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
GRAPHQL;
    }

    public function handleBuilder($builder, $value): object
    {
        return $builder->where(
            $this->directiveArgValue('key') ?? $this->nodeName(),
            $value
        );
    }

    public function handleScoutBuilder(ScoutBuilder $builder, $value)
    {
        return $builder->where(
            $this->directiveArgValue('key') ?? $this->nodeName(),
            $value
        );
    }
}
