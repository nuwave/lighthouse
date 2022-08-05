<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;

class WhereDirective extends BaseDirective implements ArgBuilderDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Use an input value as a [where filter](https://laravel.com/docs/queries#where-clauses).
"""
directive @where(
  """
  Specify the operator to use within the WHERE condition.
  """
  operator: String = "="

  """
  Specify the database column to compare.
  Only required if database column has a different name than the attribute in your schema.
  """
  key: String

  """
  Use Laravel's where clauses upon the query builder.
  This only works for clauses with the signature (string $column, string $operator, mixed $value).
  """
  clause: String
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
GRAPHQL;
    }

    public function handleBuilder($builder, $value): object
    {
        // Allow users to overwrite the default "where" clause, e.g. "whereYear"
        $clause = $this->directiveArgValue('clause', 'where');

        return $builder->{$clause}(
            $this->directiveArgValue('key', $this->nodeName()),
            $this->directiveArgValue('operator', '='),
            $value
        );
    }
}
