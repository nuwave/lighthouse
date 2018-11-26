<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface ArgMiddleware extends Directive
{
    /**
     * Apply transformations on the ArgumentValue.
     *
     * @param mixed    $argumentValue
     * @param \Closure $next
     *
     * @return mixed
     */
    public function handleArgument($argumentValue, \Closure $next);
}
