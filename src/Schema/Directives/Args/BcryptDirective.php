<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Nuwave\Lighthouse\Support\Contracts\ArgMiddleware;

class BcryptDirective implements ArgMiddleware
{
    /**
     * Directive name.
     *
     * @return string
     */
    public function name(): string
    {
        return 'bcrypt';
    }

    /**
     * Apply transformations on the ArgumentValue.
     *
     * @param string   $argumentValue
     * @param \Closure $next
     *
     * @return mixed
     */
    public function handleArgument($argumentValue, \Closure $next)
    {
        return $next(bcrypt($argumentValue));
    }
}
