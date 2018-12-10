<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface ArgTransformerDirective extends ArgDirective
{
    /**
     * Apply transformations on the ArgumentValue.
     *
     * @param mixed $argumentValue
     *
     * @return mixed
     */
    public function transform($argumentValue);
}
