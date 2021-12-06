<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;

class WhereJsonContainsDirective extends BaseDirective implements ArgBuilderDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Use an input value as a [whereJsonContains filter](https://laravel.com/docs/queries#json-where-clauses).
"""
directive @whereJsonContains(
  """
  Specify the database column and path inside the JSON to compare.
  Only required if database column has a different name than the attribute in your schema.
  """
  key: String
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
GRAPHQL;
    }

    public function handleBuilder($builder, $value): object
    {
        return $builder->whereJsonContains(
            $this->directiveArgValue('key', $this->nodeName()),
            $value
        );
    }
}
