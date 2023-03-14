<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Execution\Arguments\ResolveNested;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;
use Nuwave\Lighthouse\Support\Utils;

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

    /**
     * Delegate to nested arg resolvers.
     *
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet|array<\Nuwave\Lighthouse\Execution\Arguments\ArgumentSet>  $args  the slice of arguments that belongs to this nested resolver
     */
    public function __invoke(mixed $root, $args): mixed
    {
        $resolveNested = new ResolveNested();

        return Utils::mapEach(
            static fn (ArgumentSet $argumentSet): mixed => $resolveNested($root, $argumentSet),
            $args,
        );
    }
}
