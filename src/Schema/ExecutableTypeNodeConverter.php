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
        switch ($nodeName) {
            case 'ID':
                return Type::id();
            case 'Int':
                return Type::int();
            case 'Boolean':
                return Type::boolean();
            case 'Float':
                return Type::float();
            case 'String':
                return Type::string();
            default:
                return $this->typeRegistry->get($nodeName);
        }
    }
}
