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
     * @param ArgumentValue $argumentValue
     * @param \Closure       $next
     *
     * @return ArgumentValue
     */
    public function handleArgument(ArgumentValue $argumentValue, \Closure $next): ArgumentValue
    {
        $argumentValue->addTransformer(
            function ($password) {
                return bcrypt($password);
            }
        );
        
        return $next($argumentValue);
    }
}
