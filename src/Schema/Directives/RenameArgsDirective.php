<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class RenameArgsDirective extends BaseDirective implements ArgDirective, FieldMiddleware
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Apply the @rename directives on the incoming arguments.
"""
directive @renameArgs on FIELD_DEFINITION
SDL;
    }

    public function handleField(FieldValue $fieldValue, Closure $next)
    {
        $resolver = $fieldValue->getResolver();

        return $next(
            $fieldValue->setResolver(
                function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver) {
                    $resolveInfo->argumentSet = $resolveInfo->argumentSet->rename();

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
}
