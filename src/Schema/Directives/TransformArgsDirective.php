<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\ArgTransformerDirective;
use Nuwave\Lighthouse\Support\Contracts\Directive;

class TransformArgsDirective extends ArgTraversalDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Transform the arguments of a field.
"""
directive @transformArgs on FIELD_DEFINITION
GRAPHQL;
    }

    protected function applyDirective(Directive $directive, $value)
    {
        if ($directive instanceof ArgTransformerDirective) {
            return $directive->transform($value);
        }

        return $value;
    }
}
