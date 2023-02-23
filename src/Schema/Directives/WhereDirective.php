<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class WhereDirective extends BaseDirective implements ArgBuilderDirective, FieldBuilderDirective
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

  """
  Provide a value to compare against.
  Exclusively required when this directive is used on a field.
  """
  value: WhereValue
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION | FIELD_DEFINITION

"""
Any constant literal value: https://graphql.github.io/graphql-spec/draft/#sec-Input-Values
"""
scalar WhereValue
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

    public function handleFieldBuilder(object $builder, $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): object
    {
        return $this->handleBuilder(
            $builder,
            $this->directiveArgValue('value')
        );
    }
}
