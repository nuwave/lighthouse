<?php


namespace Nuwave\Lighthouse\Types;


use Nuwave\Lighthouse\Schema\Contracts\TypeWrapper;
use Nuwave\Lighthouse\Schema\DirectiveRegistry;
use Nuwave\Lighthouse\Schema\Traits\HasWrappedType;

class ListType extends Type implements TypeWrapper
{
    use HasWrappedType;

    /**
     * NonNullType constructor.
     *
     * @param DirectiveRegistry $directiveRegistry
     * @param Type $ofType
     */
    public function __construct(DirectiveRegistry $directiveRegistry, Type $ofType)
    {
        parent::__construct($directiveRegistry,null, null);
        $this->ofType = $ofType;
    }

    public static function ofType(DirectiveRegistry $directiveRegistry, Type $type)
    {
        return new ListType($directiveRegistry, $type);
    }
}
