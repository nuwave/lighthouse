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

    /**
     * @param  mixed  $type
     * @return \GraphQL\Type\Definition\NonNull|mixed
     */
    protected function nonNull($type)
    {
        return Type::nonNull($type);
    }

    /**
     * @param  mixed  $type
     * @return \GraphQL\Type\Definition\ListOfType|mixed
     */
    protected function listOf($type)
    {
        return Type::listOf($type);
    }

    /**
     * @param  string  $nodeName
     * @return \GraphQL\Type\Definition\Type|mixed
     */
    protected function namedType(string $nodeName)
    {
        return Type::getStandardTypes()[$nodeName]
            ?? $this->typeRegistry->get($nodeName);
    }
}
