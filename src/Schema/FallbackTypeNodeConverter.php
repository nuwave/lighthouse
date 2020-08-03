<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\AST\TypeNodeConverter;

class FallbackTypeNodeConverter extends TypeNodeConverter
{
    /**
     * @var \Nuwave\Lighthouse\Schema\TypeRegistry
     */
    protected $typeRegistry;

    public function __construct(TypeRegistry $typeRegistry)
    {
        $this->typeRegistry = $typeRegistry;
    }

    protected function nonNull($type): NonNull
    {
        return Type::nonNull($type);
    }

    protected function listOf($type): ListOfType
    {
        return Type::listOf($type);
    }

    protected function namedType(string $nodeName): Type
    {
        if (isset(Type::getStandardTypes()[$nodeName])) {
            return Type::getStandardTypes()[$nodeName];
        }

        if (! $this->typeRegistry->has($nodeName)) {
            $dummyType = new CustomScalarType([
                'name' => $nodeName,
                'serialize' => function () {
                },
            ]);
            $this->typeRegistry->register($dummyType);
        }

        return $this->typeRegistry->get($nodeName);
    }
}
