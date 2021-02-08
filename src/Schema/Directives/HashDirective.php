<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Contracts\Hashing\Hasher;
use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgTransformerDirective;

class HashDirective extends BaseDirective implements ArgTransformerDirective, ArgDirective
{
    /**
     * @var \Illuminate\Contracts\Hashing\Hasher
     */
    protected $hasher;

    public function __construct(Hasher $hasher)
    {
        $this->hasher = $hasher;
    }

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Use Laravel hashing to transform an argument value.

Useful for hashing passwords before inserting them into the database.
This uses the default hashing driver defined in `config/hashing.php`.
"""
directive @hash on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
GRAPHQL;
    }

    /**
     * @param  string  $argumentValue
     */
    public function transform($argumentValue): string
    {
        return $this->hasher->make($argumentValue);
    }
}
