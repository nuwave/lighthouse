<?php


namespace Nuwave\Lighthouse\Types;


class ListType extends Type
{
    protected $ofType;

    /**
     * NonNullType constructor.
     *
     * @param $ofType
     */
    public function __construct($ofType)
    {
        parent::__construct("", "");
        $this->ofType = $ofType;
    }

    public function getWrappedType() : Type
    {
        return $this->ofType;
    }
}