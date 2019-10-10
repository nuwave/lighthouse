<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\AST\TypeNodeConverter;

class ExecutableTypeNodeConverter extends TypeNodeConverter
{
    /**
     * @var \Nuwave\Lighthouse\Schema\TypeRegistry
     */
    protected $typeRegistry;

    /**
     * @param  \Nuwave\Lighthouse\Schema\TypeRegistry  $typeRegistry
     * @return void
     */
    public function __construct(TypeRegistry $typeRegistry)
    {
        $this->typeRegistry = $typeRegistry;
    }

    protected function nonNull($type)
    {
        return Type::nonNull($type);
    }

    protected function listOf($type)
    {
        return Type::listOf($type);
    }

    protected function namedType(string $nodeName)
    {
        return Type::getStandardTypes()[$nodeName]
            ?? $this->typeRegistry->get($nodeName);
    }
}
