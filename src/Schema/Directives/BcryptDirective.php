<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\ArgTransformerDirective;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;

/**
 * @deprecated
 */
class BcryptDirective extends BaseDirective implements ArgTransformerDirective, DefinedDirective
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
