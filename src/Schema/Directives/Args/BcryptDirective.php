<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Nuwave\Lighthouse\Schema\Values\ArgumentValue;

class BcryptDirective implements ArgMiddleware
{
    /**
     * Directive name.
     *
     * @return string
     */
    public static function name()
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
