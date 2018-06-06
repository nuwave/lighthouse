<?php


namespace Nuwave\Lighthouse\Types\Scalar;


class IntType extends ScalarType
{
    public function __construct()
    {
        parent::__construct(
            'Int',
            "The `Int` scalar type represents non-fractional signed whole numeric values. Int can represent values between -(2^31) and 2^31 - 1."
        );
    }

    public static function instance() : IntType
    {
        return new IntType();
    }
}
