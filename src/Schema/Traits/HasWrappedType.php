<?php


namespace Nuwave\Lighthouse\Schema\Traits;


use Nuwave\Lighthouse\Schema\Contracts\TypeWrapper;
use Nuwave\Lighthouse\Types\Type;

trait HasWrappedType
{
    protected $ofType;

    public function getWrappedType() : Type
    {
        return $this->ofType;
    }

    /**
     * Recursively look until we get to a real type and not a type wrapper.
     *
     * @return Type
     */
    public function getUnderlyingType() : Type
    {
        $type = $this;

        do {
            $type = $type->getWrappedType();
        } while($type instanceof TypeWrapper);

        return $type;
    }
}