<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgTransformerDirective;

/**
 * @deprecated in favor of @hash
 * @see \Nuwave\Lighthouse\Schema\Directives\HashDirective
 */
class BcryptDirective extends BaseDirective implements ArgTransformerDirective, ArgDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Run the `bcrypt` function on the argument it is defined on.

@deprecated(reason: "Use @hash instead. This directive will be removed in v5.")
"""
directive @bcrypt on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
SDL;
    }

    /**
     * Run Laravel's bcrypt helper on the argument.
     *
     * Useful for hashing passwords before inserting them into the database.
     *
     * @param  string  $argumentValue
     */
    public function transform($argumentValue): string
    {
        return bcrypt($argumentValue);
    }
}
