<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\WrappingType;

class DefinitionHelper
{
    public function getContainedType(Type $type)
    {
        if($type instanceof WrappingType) {
            return $type->getWrappedType();
        }


    }
}
