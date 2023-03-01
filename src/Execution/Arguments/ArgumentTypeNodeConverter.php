<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution\Arguments;

use Nuwave\Lighthouse\Schema\AST\TypeNodeConverter;

class ArgumentTypeNodeConverter extends TypeNodeConverter
{
    protected function nonNull(mixed $type): ListType|NamedType
    {
        $type->nonNull = true;

        return $type;
    }

    protected function listOf(mixed $type): ListType
    {
        return new ListType($type);
    }

    protected function namedType(string $nodeName): NamedType
    {
        return new NamedType($nodeName);
    }
}
