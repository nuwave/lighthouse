<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Contracts\Database\Query\Builder;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class WhereNullDirective extends BaseDirective implements ArgBuilderDirective, FieldBuilderDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Filter the value is null.
"""
directive @whereNull(
  """
  Specify the database column to compare.
  Only required if database column has a different name than the attribute in your schema.
  """
  key: String

  """
  Should the value be null?
  Exclusively required when this directive is used on a field.
  """
  value: Boolean
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION | FIELD_DEFINITION
GRAPHQL;
    }

    public function handleBuilder(Builder $builder, $value): Builder
    {
        if ($value === null) {
            return $builder;
        }

        return $builder->whereNull(
            $this->directiveArgValue('key', $this->nodeName()),
            'and',
            ! $value,
        );
    }

    public function handleFieldBuilder(Builder $builder, mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): Builder
    {
        return $this->handleBuilder(
            $builder,
            $this->directiveArgValue('value'),
        );
    }
}
