<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use Nuwave\Lighthouse\Schema\AST\TypeNodeConverter;

class ArgumentTypeNodeConverter extends TypeNodeConverter
{
    /**
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ListType|\Nuwave\Lighthouse\Execution\Arguments\NamedType  $type
     *
     * @return \Nuwave\Lighthouse\Execution\Arguments\ListType|\Nuwave\Lighthouse\Execution\Arguments\NamedType
     */
    protected function nonNull($type): object
    {
        $type->nonNull = true;

        return $type;
    }

    /**
     * @param  \Nuwave\Lighthouse\Execution\Arguments\NamedType  $type
     *
     * @return \Nuwave\Lighthouse\Execution\Arguments\ListType
     */
    protected function listOf($type): object
    {
        return new ListType($type);
    }

    protected function namedType(string $nodeName): NamedType
    {
        return new NamedType($nodeName);
    }
}
