<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Utils;

class RenameArgsDirective extends BaseDirective implements FieldMiddleware
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Apply the @rename directives on the incoming arguments.
"""
directive @renameArgs on FIELD_DEFINITION
GRAPHQL;
    }

    public function handleField(FieldValue $fieldValue, Closure $next)
    {
        $resolver = $fieldValue->getResolver();

        $fieldValue->setResolver(function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver) {
            $argumentSet = $resolveInfo->argumentSet;
            $this->rename($argumentSet);

            return $resolver(
                $root,
                $argumentSet->toArray(),
                $context,
                $resolveInfo
            );
        });

        return $next($fieldValue);
    }

    protected function rename(ArgumentSet &$argumentSet): void
    {
        foreach ($argumentSet->arguments as $name => $argument) {
            // Recursively apply the renaming to nested inputs.
            // We look for further ArgumentSet instances, they
            // might be contained within an array.
            Utils::applyEach(
                function ($value) {
                    if ($value instanceof ArgumentSet) {
                        $this->rename($value);
                    }
                },
                $argument->value
            );

            $maybeRenameDirective = $argument->directives->first(function (Directive $directive): bool {
                return $directive instanceof RenameDirective;
            });

            if ($maybeRenameDirective instanceof RenameDirective) {
                $argumentSet->arguments[$maybeRenameDirective->attributeArgValue()] = $argument;
                unset($argumentSet->arguments[$name]);
            }
        }
    }
}
