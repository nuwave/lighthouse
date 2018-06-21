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
    public function name()
    {
        return 'bcrypt';
    }

    /**
     * Resolve the field directive.
     *
     * @param ArgumentValue $value
     *
     * @return ArgumentValue
     */
    public function handleArgument(ArgumentValue $value)
    {
        return $value->setResolver(function ($password) {
            return bcrypt($password);
        });
    }
}
