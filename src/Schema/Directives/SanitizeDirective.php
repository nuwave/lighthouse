<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\ArgSanitizerDirective;
use Nuwave\Lighthouse\Support\Contracts\Directive;

class SanitizeDirective extends ArgTraversalDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Apply sanitization to the arguments of a field.
"""
directive @sanitize on FIELD_DEFINITION
GRAPHQL;
    }

    protected function applyDirective(Directive $directive, $value)
    {
        if ($directive instanceof ArgSanitizerDirective) {
            return $directive->sanitize($value);
        }

        return $value;
    }
}
