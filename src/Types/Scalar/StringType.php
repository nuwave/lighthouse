<?php


namespace Nuwave\Lighthouse\Types\Scalar;


class StringType extends ScalarType
{
    public function __construct()
    {
        parent::__construct(
            'String',
            'The `String` scalar type represents textual data, represented as UTF-8
character sequences. The String type is most often used by GraphQL to
represent free-form human-readable text.'
        );
    }

    public static function instance() : StringType
    {
        return new StringType();
    }
}
