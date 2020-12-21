<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;

class LikeDirective extends BaseDirective implements ArgBuilderDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
enum LikePercentageLocation {
    START
    END
    BOTH
}

"""
Uses a LIKE query.
"""
directive @like(
  """
  Specify the database column to compare.
  Only required if database column has a different name than the attribute in your schema.
  """
  key: String

  """
  Specify the positions of the % in the LIKE comparison. The default is BOTH.
  """
  percentage: LikePercentageLocation
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
GRAPHQL;
    }

    protected function escapePercentage(string $value, string $char = '\\'): string
    {
        return str_replace(
            [$char, '%', '_'],
            [$char.$char, $char.'%', $char.'_'],
            $value
        );
    }

    /**
     * Apply a "WHERE LIKE $value" clause.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function handleBuilder($builder, $value): object
    {
        $valueEscaped = $this->escapePercentage($value);

        $percentage = $this->directiveArgValue('key', $this->nodeName(), 'BOTH');
        switch ($percentage) {
            case 'START':
                $valueEscaped = '%'.$valueEscaped;
                break;
            case 'END':
                $valueEscaped = $valueEscaped.'%';
                break;
            case 'BOTH';
                $valueEscaped = '%'.$valueEscaped.'%';
                break;
        }

        return $builder->where(
            $this->directiveArgValue('key', $this->nodeName()),
            'LIKE',
            $valueEscaped
        );
    }
}
