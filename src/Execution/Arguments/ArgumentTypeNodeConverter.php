<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use Nuwave\Lighthouse\Schema\AST\TypeNodeConverter;

class ArgumentTypeNodeConverter extends TypeNodeConverter
{
    /**
     * @param \Nuwave\Lighthouse\Execution\Arguments\ListType|\Nuwave\Lighthouse\Execution\Arguments\NamedType $type
     * @return \Nuwave\Lighthouse\Execution\Arguments\ListType|\Nuwave\Lighthouse\Execution\Arguments\NamedType
     */
    protected function nonNull($type)
    {
        $type->nonNull = true;

        return $type;
    }

    /**
     * @param  \Nuwave\Lighthouse\Execution\Arguments\NamedType  $type
     * @return \Nuwave\Lighthouse\Execution\Arguments\ListType
     */
    protected function listOf($type)
    {
        return new ListType($type);
    }

    /**
     * @return \Nuwave\Lighthouse\Execution\Arguments\NamedType
     */
    protected function namedType(string $nodeName): NamedType
    {
        return new NamedType($nodeName);
    }
}
