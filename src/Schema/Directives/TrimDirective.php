<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgSanitizerDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Utils;

class TrimDirective extends BaseDirective implements ArgSanitizerDirective, ArgDirective, FieldMiddleware
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Remove whitespace from the beginning and end of a given input.

This can be used on:
- a single argument or input field to sanitize that subtree
- a field to trim all strings
"""
directive @trim on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION | FIELD_DEFINITION
GRAPHQL;
    }

    /**
     * Remove whitespace from the beginning and end of a given input.
     */
    public function sanitize($argumentValue)
    {
        return Utils::mapEach(
            function ($value) {
                return $value instanceof ArgumentSet
                    ? $this->transformArgumentSet($value)
                    : $this->transformLeaf($value);
            },
            $argumentValue
        );
    }

    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $resolver = $fieldValue->getResolver();

        $fieldValue->setResolver(function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver) {
            $resolveInfo->argumentSet = $this->transformArgumentSet($resolveInfo->argumentSet);

            return $resolver(
                $root,
                $resolveInfo->argumentSet->toArray(),
                $context,
                $resolveInfo
            );
        });

        return $next($fieldValue);
    }

    protected function transformArgumentSet(ArgumentSet $argumentSet): ArgumentSet
    {
        foreach ($argumentSet->arguments as $argument) {
            $argument->value = $this->sanitize($argument->value);
        }

        return $argumentSet;
    }

    /**
     * @param  mixed  $value  The client given value
     *
     * @return mixed The transformed value
     */
    protected function transformLeaf($value)
    {
        if (is_string($value)) {
            return trim($value);
        }

        return $value;
    }
}
