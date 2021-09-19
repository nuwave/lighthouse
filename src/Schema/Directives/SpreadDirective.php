<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Execution\Arguments\Argument;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class SpreadDirective extends BaseDirective implements FieldMiddleware
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Merge the fields of a nested input object into the arguments of its parent
when processing the field arguments given by a client.
"""
directive @spread(
  """
  A reference to the resolver function to be used.
  Consists of two parts: a class name and a method name, seperated by an `@` symbol.
  If you pass only a class name, the method name defaults to `__invoke`.
  """
  resolver: String
) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
GRAPHQL;
    }

    public function handleField(FieldValue $fieldValue, Closure $next)
    {
        $resolver = $fieldValue->getResolver();

        return $next(
            $fieldValue->setResolver(
                function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver) {
                    $resolveInfo->argumentSet = $resolveInfo->argumentSet->spread();

                    return $resolver(
                        $root,
                        $resolveInfo->argumentSet->toArray(),
                        $context,
                        $resolveInfo
                    );
                }
            )
        );
    }

    public function transformArgumentSet(ArgumentSet $argumentSet, string $argumentName, Argument $argument): ArgumentSet
    {
        if ($argument->value instanceof ArgumentSet) {
            $arguments = $argument->value->arguments;

            if ($this->directiveHasArgument('resolver')) {
                $resolver = $this->getResolverFromArgument('resolver');
                $arguments = [];

                foreach ($argument->value->arguments as $name => $argument) {
                    $arguments[$resolver($argumentName, $name)] = $argument;
                }
            }

            $argumentSet->arguments += $arguments;
        } else {
            $argumentSet->arguments[$argumentName] = $argument;
        }

        return $argumentSet;
    }
}
