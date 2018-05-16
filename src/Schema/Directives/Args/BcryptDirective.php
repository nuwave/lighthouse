<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Closure;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use Nuwave\Lighthouse\Support\Contracts\ArgMiddleware;

class BcryptDirective implements ArgMiddleware
{
    /**
     * Directive name.
     *
     * @return string
     */
    public function name()
    {
        return 'bcrypt';
    }

    /**
     * Resolve the field directive.
     *
     * @param ArgumentValue $value
     *
     * @param Closure $next
     * @return ArgumentValue
     */
    public function handleArgument(ArgumentValue $value, Closure $next)
    {
        $value->setResolver(function ($password) {
            return bcrypt($password);
        });
        
        return $next($value);
    }
}
