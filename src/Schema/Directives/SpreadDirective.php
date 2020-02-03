<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\ArgDirective;

class SpreadDirective extends BaseDirective implements ArgDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Merge the fields of a nested input object into the arguments of its parent
when processing the field arguments given by a client.
"""
directive @spread on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
SDL;
    }
}
