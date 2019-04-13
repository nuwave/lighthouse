<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\ArgTransformerDirective;

class BcryptDirective implements ArgTransformerDirective
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
     * Run Laravel's bcrypt helper on the argument.
     *
     * Useful for hashing passwords before inserting them into the database.
     *
     * @param  string  $argumentValue
     * @return mixed
     */
    public function transform($argumentValue): string
    {
        return bcrypt($argumentValue);
    }
}
