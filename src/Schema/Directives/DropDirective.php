<?php

namespace Nuwave\Lighthouse\Schema\Directives;

class DropDirective extends BaseDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Ignore the user given value, don't pass it to the resolver.
"""
directive @drop on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
GRAPHQL;
    }
}
