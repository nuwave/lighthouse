<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;

class WhereNotBetweenDirective extends BaseDirective implements ArgBuilderDirective, DefinedDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Verify that a column\'s value lies outside of two values.
The type of the input value this is defined upon should be
an `input` object with two fields.
"""
directive @whereNotBetween(
  """
  Specify the database column to compare.
  Only required if database column has a different name than the attribute in your schema.
  """
  key: String
) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
SDL;
    }

    /**
     * Apply a "WHERE NOT BETWEEN" clause.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function handleBuilder($builder, $values)
    {
        return $builder->whereNotBetween(
            $this->directiveArgValue('key', $this->nodeName()),
            $values
        );
    }
}
