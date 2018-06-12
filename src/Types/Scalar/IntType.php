<?php


namespace Nuwave\Lighthouse\Types\Scalar;


use Nuwave\Lighthouse\Schema\DirectiveRegistry;

class IntType extends ScalarType
{
    public function __construct(DirectiveRegistry $directiveRegistry)
    {
        parent::__construct(
             $directiveRegistry,
            'Int',
            "The `Int` scalar type represents non-fractional signed whole numeric values. Int can represent values between -(2^31) and 2^31 - 1."
        );
    }

    public static function instance() : IntType
    {
        return app(IntType::class);
    }
}
