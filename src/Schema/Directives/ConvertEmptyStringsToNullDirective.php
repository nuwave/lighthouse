<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\ScalarType;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgSanitizerDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Utils;

class ConvertEmptyStringsToNullDirective extends BaseDirective implements ArgSanitizerDirective, ArgDirective, FieldMiddleware
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Replaces `""` with `null`.
"""
directive @convertEmptyStringsToNull on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION | FIELD_DEFINITION
GRAPHQL;
    }

    public function sanitize($argumentValue)
    {
        return Utils::mapEachRecursive(
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
            $namedType = $argument->namedType();
            if (
                null !== $namedType
                && ScalarType::STRING === $namedType->name
                && ! $namedType->nonNull
            ) {
                $argument->value = $this->sanitize($argument->value);
            }
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
        if ('' === $value) {
            return null;
        }

        return $value;
    }
}
