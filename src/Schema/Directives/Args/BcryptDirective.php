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
    public function name(): string
    {
        return 'bcrypt';
    }

    /**
     * Apply transformations on the ArgumentValue.
     *
     * @param ArgumentValue $value
     * @param \Closure       $next
     *
     * @return ArgumentValue
     */
    public function handleArgument(ArgumentValue $value, \Closure $next): ArgumentValue
    {
        return $next(
            $value->setResolver(
                function ($password) {
                    return bcrypt($password);
                }
            )
        );
    }
}
