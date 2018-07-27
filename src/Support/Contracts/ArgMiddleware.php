<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Nuwave\Lighthouse\Schema\Values\ArgumentValue;

interface ArgMiddleware extends Directive
{
    /**
     * Apply transformations on the ArgumentValue.
     *
     * @param ArgumentValue $argument
     * @param \Closure       $next
     *
     * @return ArgumentValue
     */
    public function handleArgument(ArgumentValue $argument, \Closure $next);
}
