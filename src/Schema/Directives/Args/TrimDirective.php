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
     * Apply transformations on the ArgumentValue.
     *
     * @param string $argumentValue
     *
     * @return mixed
     */
    public function transform($argumentValue)
    {
        return \is_string($argumentValue) ?
                trim($argumentValue) :
                $argumentValue;
    }
}
