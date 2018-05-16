<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Closure;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;

interface ArgMiddleware
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name();

    /**
     * Resolve the field directive.
     *
     * @param ArgumentValue $argument
     *
     * @param Closure $next
     * @return ArgumentValue
     */
    public function handleArgument(ArgumentValue $argument, Closure $next);
}
