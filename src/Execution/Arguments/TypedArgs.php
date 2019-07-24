<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use GraphQL\Type\Definition\InputType;

class TypedArgs extends \ArrayObject
{
    public function __construct($input = array(), $flags = 0, $iterator_class = "ArrayIterator")
    {
        parent::__construct($input, $flags, $iterator_class);
    }

    public static function fromArgs(array $input, )
    {

    }

    public function type(string $offset): InputType
    {
        $this->
    }
}
