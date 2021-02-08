<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldBuilderDirective;

class BuilderDirective extends BaseDirective implements ArgBuilderDirective, FieldBuilderDirective
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
  value: Mixed
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION | FIELD_DEFINITION
GRAPHQL;
    }

    public function handleBuilder($builder, $value): object
    {
        $resolver = $this->getResolverFromArgument('method');

        return $resolver($builder, $value, $this->definitionNode);
    }

    public function handleFieldBuilder(object $builder): object
    {
        $resolver = $this->getResolverFromArgument('method');

        if ($this->directiveHasArgument('value')) {
            return $resolver(
                $builder,
                $this->directiveArgValue('value')
            );
        }

        return $resolver($builder);
    }
}
