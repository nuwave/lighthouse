<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgMiddleware;

class BcryptDirective extends BaseDirective implements ArgMiddleware
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
     * @param \Closure       $next
     *
     * @return ArgumentValue
     */
    public function handleArgument(ArgumentValue $value, \Closure $next)
    {
        return $next($value->setResolver(function ($password) {
            return bcrypt($password);
        }));
    }
}
