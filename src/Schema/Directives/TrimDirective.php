<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\ArgTransformerDirective;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;

class TrimDirective extends BaseDirective implements ArgTransformerDirective, DefinedDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Run the `trim` function on an input value.
"""
directive @trim on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
SDL;
    }

    /**
     * Remove whitespace from the beginning and end of a given input.
     *
     * @param  string  $argumentValue
     */
    public function transform($argumentValue): string
    {
        return trim($argumentValue);
    }
}
