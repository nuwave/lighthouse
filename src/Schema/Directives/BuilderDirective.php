<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Laravel\Scout\Builder as ScoutBuilder;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Scout\ScoutBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class BuilderDirective extends BaseDirective implements ArgBuilderDirective, ScoutBuilderDirective, FieldBuilderDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Manipulate the query builder with a method.
"""
directive @builder(
  """
  Reference a method that is passed the query builder.
  Consists of two parts: a class name and a method name, separated by an `@` symbol.
  If you pass only a class name, the method name defaults to `__invoke`.
  """
  method: String!

  """
  Pass a value to the method as the second argument after the query builder.
  Only used when the directive is added on a field.
  """
  value: BuilderValue
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION | FIELD_DEFINITION

"""
Any constant literal value: https://graphql.github.io/graphql-spec/draft/#sec-Input-Values
"""
scalar BuilderValue
GRAPHQL;
    }

    public function handleBuilder(QueryBuilder|EloquentBuilder|Relation $builder, $value): QueryBuilder|EloquentBuilder|Relation
    {
        $resolver = $this->resolver();

        return $resolver($builder, $value, $this->definitionNode);
    }

    public function handleScoutBuilder(ScoutBuilder $builder, mixed $value): ScoutBuilder
    {
        $resolver = $this->resolver();

        return $resolver($builder, $value, $this->definitionNode);
    }

    public function handleFieldBuilder(QueryBuilder|EloquentBuilder|Relation $builder, mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): QueryBuilder|EloquentBuilder|Relation
    {
        $resolver = $this->resolver();

        if ($this->directiveHasArgument('value')) {
            return $resolver(
                $builder,
                $this->directiveArgValue('value'),
                $root,
                $args,
                $context,
                $resolveInfo,
            );
        }

        return $resolver($builder, null, $root, $args, $context, $resolveInfo);
    }

    protected function resolver(): \Closure
    {
        return $this->getResolverFromArgument('method');
    }
}
