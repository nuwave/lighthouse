<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\ArgDirective;

class SpreadDirective implements ArgDirective
{
    /**
     * Directive name.
     *
     * @return string
     */
    public function name(): string
    {
        return 'spread';
    }

    public static function definition(): string
    {
        return '
"""
Spread out the nested values of an argument of type input object into it\'s parent.
"""
directive @spread on ARGUMENT_DEFINITION';
    }
}
