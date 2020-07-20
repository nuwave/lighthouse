<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface ArgTransformerDirective extends Directive
{
    /**
     * Apply transformations on the value of an argument given to a field.
     *
     * @param  mixed  $argumentValue The client given value.
     * @return mixed The transformed value.
     */
    public function transform($argumentValue);
}
