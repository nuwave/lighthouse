<?php


namespace Nuwave\Lighthouse\Schema\Contracts;


use Nuwave\Lighthouse\Types\Type;

interface TypeWrapper
{
    public function getWrappedType() : Type;

    public function getUnderlyingType() : Type;
}
