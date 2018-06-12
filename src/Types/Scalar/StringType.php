<?php


namespace Nuwave\Lighthouse\Types\Scalar;


use Nuwave\Lighthouse\Schema\DirectiveRegistry;

class StringType extends ScalarType
{
    public function __construct(DirectiveRegistry $directiveRegistry)
    {
        parent::__construct(
            $directiveRegistry,
            'String',
            'The `String` scalar type represents textual data, represented as UTF-8
character sequences. The String type is most often used by GraphQL to
represent free-form human-readable text.'
        );
    }

    public static function instance() : StringType
    {
        return app(StringType::class);
    }
}
