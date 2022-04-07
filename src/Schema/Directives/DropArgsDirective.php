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

class DropArgsDirective extends BaseDirective implements FieldMiddleware
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Apply the @drop directives on the incoming arguments.
"""
directive @dropArgs on FIELD_DEFINITION
GRAPHQL;
    }

    public function handleField(FieldValue $fieldValue, Closure $next)
    {
        $resolver = $fieldValue->getResolver();

        $fieldValue->setResolver(function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver) {
            $argumentSet = $resolveInfo->argumentSet;
            $this->drop($argumentSet);

            return $resolver(
                $root,
                $argumentSet->toArray(),
                $context,
                $resolveInfo
            );
        });

        return $next($fieldValue);
    }

    protected function drop(ArgumentSet &$argumentSet): void
    {
        foreach ($argumentSet->arguments as $name => $argument) {
            $maybeDropDirective = $argument->directives->first(function (Directive $directive): bool {
                return $directive instanceof DropDirective;
            });

            if ($maybeDropDirective instanceof DropDirective) {
                unset($argumentSet->arguments[$name]);
            } else {
                // Recursively remove nested inputs using @drop directive.
                // We look for further ArgumentSet instances, they
                // might be contained within an array.
                Utils::applyEach(
                    function ($value) {
                        if ($value instanceof ArgumentSet) {
                            $this->drop($value);
                        }
                    },
                    $argument->value
                );
            }
        }
    }
}
