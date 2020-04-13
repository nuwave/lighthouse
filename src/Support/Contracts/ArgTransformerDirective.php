<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface ArgTransformerDirective extends ArgDirective
{
    /**
     * Apply transformations on the value of an argument given to a field.
     */
    public function transform($argumentValue);
}
