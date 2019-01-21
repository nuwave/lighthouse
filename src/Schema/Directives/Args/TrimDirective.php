<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Nuwave\Lighthouse\Support\Contracts\ArgTransformerDirective;

class TrimDirective implements ArgTransformerDirective
{
    /**
     * Directive name.
     *
     * @return string
     */
    public function name(): string
    {
        return 'trim';
    }

    /**
     * Remove whitespace from the beginning and end of a given input.
     *
     * @param  string  $argumentValue
     *
     * @return mixed
     */
    public function transform($argumentValue): string
    {
        return trim($argumentValue);
    }
}
