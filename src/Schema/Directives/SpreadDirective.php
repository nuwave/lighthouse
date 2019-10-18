<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;

class SpreadDirective implements ArgDirective, DefinedDirective
{
    const NAME = 'spread';

    /**
     * Directive name.
     *
     * @return string
     */
    public function name(): string
    {
        return self::NAME;
    }

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'SDL'
"""
Merge the fields of a nested input object into the arguments of its parent
when processing the field arguments given by a client.
"""
directive @spread on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
SDL;
    }
}
