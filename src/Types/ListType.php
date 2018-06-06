<?php


namespace Nuwave\Lighthouse\Types;


use Nuwave\Lighthouse\Schema\Contracts\TypeWrapper;
use Nuwave\Lighthouse\Schema\Traits\HasWrappedType;

class ListType extends Type implements TypeWrapper
{
    use HasWrappedType;

    /**
     * NonNullType constructor.
     *
     * @param $ofType
     */
    public function __construct($ofType)
    {
        parent::__construct(null, null);
        $this->ofType = $ofType;
    }

    public static function ofType(Type $type)
    {
        return new ListType($type);
    }
}
