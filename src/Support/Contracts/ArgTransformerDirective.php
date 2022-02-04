<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface ArgTransformerDirective extends Directive
{
    /**
     * Apply transformations on the value of an argument given to a field.
     *
     * @param  mixed  $argumentValue  the client given value
     *
     * @return mixed the transformed value
     */
    public function transform($argumentValue);
}
