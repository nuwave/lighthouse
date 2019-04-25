<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface ArgTransformerDirective extends ArgDirective
{
    /**
     * Apply transformations on the value of an argument given to a field.
     *
     * @param  mixed  $argumentValue
     * @return mixed
     */
    public function transform($argumentValue);
}
