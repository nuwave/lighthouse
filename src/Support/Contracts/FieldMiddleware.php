<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Closure;
use Nuwave\Lighthouse\Execution\ResolverArguments;

interface FieldMiddleware extends Directive
{
    /**
     * Wrap around the final field resolver.
     *
     * @param  \Closure(\Nuwave\Lighthouse\Execution\ResolverArguments $field): mixed  $next
     */
    public function handleField(ResolverArguments $arguments, Closure $next);
}
