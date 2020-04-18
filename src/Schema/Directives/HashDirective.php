<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Contracts\Hashing\Hasher;
use Nuwave\Lighthouse\Support\Contracts\ArgTransformerDirective;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;

class HashDirective extends BaseDirective implements ArgTransformerDirective, DefinedDirective
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
        return /** @lang GraphQL */ <<<'SDL'
"""
Use Laravel hashing to transform an argument value.

Useful for hashing passwords before inserting them into the database.
This uses the default hashing driver defined in `config/hashing.php`.
"""
directive @hash on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
SDL;
    }

    /**
     * @param  string  $argumentValue
     */
    public function transform($argumentValue): string
    {
        return $this->hasher->make($argumentValue);
    }
}
