<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Contracts\Hashing\Hasher;
use Nuwave\Lighthouse\Support\Contracts\ArgTransformerDirective;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;

class HashDirective extends BaseDirective implements ArgTransformerDirective, DefinedDirective
{
    protected $hasher;

    public function __construct(Hasher $hasher)
    {
        $this->hasher = $hasher;
    }

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Run the `Hash::make` function on the argument it is defined on.
"""
directive @hash on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
SDL;
    }

    /**
     * Run Laravel's Hash::make on the argument.
     *
     * Useful for hashing passwords before inserting them into the database.
     * Uses the hashing driver defined in config/hashing.php
     *
     * @param  string  $argumentValue
     * @return string
     */
    public function transform($argumentValue): string
    {
        return $this->hasher->make($argumentValue);
    }
}
