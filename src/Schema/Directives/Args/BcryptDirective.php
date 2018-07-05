<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
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
     * Resolve the field directive.
     *
     * @param ArgumentValue $argumentValue
     *
     * @return ArgumentValue
     */
    public function handleArgument(ArgumentValue $argumentValue): ArgumentValue
    {
        return $argumentValue->setResolver(function ($password) {
            return bcrypt($password);
        });
    }
}
