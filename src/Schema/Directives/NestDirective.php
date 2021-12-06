<?php

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
     * @param  mixed  $root  The result of the parent resolver.
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet|array<\Nuwave\Lighthouse\Execution\Arguments\ArgumentSet>  $args  The slice of arguments that belongs to this nested resolver.
     */
    public function __invoke($root, $args)
    {
        $resolveNested = new ResolveNested();

        return Utils::applyEach(
            static function (ArgumentSet $argumentSet) use ($resolveNested, $root) {
                return $resolveNested($root, $argumentSet);
            },
            $args
        );
    }
}
