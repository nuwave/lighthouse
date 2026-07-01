<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\ArgResolver;

/**
 * Marker for nested input grouping — resolution is handled by ResolveNested.
 */
class NestDirective extends BaseDirective implements ArgResolver
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
A no-op nested arg resolver that delegates all calls
to the ArgResolver directives attached to the children.
"""
directive @nest on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
GRAPHQL;
    }

    /** Handled by ResolveNested — direct invocation is not supported. */
    public function __invoke(mixed $root, mixed $value): never
    {
        throw new \LogicException('NestDirective must not be invoked directly, use ResolveNested.');
    }
}
