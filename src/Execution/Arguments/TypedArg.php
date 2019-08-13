<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

class TypedArg
{
    /** @var mixed */
    public $value;

    /** @var \GraphQL\Type\Definition\FieldArgument|\GraphQL\Type\Definition\InputObjectField */
    public $definition;
}
