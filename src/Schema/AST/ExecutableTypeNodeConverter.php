<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\AST;

use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\TypeRegistry;

class ExecutableTypeNodeConverter extends TypeNodeConverter
{
    public function __construct(
        protected TypeRegistry $typeRegistry,
    ) {}

    /** @param  \GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NullableType  $type */
    protected function nonNull(mixed $type): NonNull
    {
        return Type::nonNull($type);
    }

    /**
     * @template T of \GraphQL\Type\Definition\Type
     *
     * @param  T|callable():T  $type
     *
     * @return \GraphQL\Type\Definition\ListOfType<T>
     */
    protected function listOf(mixed $type): ListOfType
    {
        return Type::listOf($type);
    }

    protected function namedType(string $nodeName): Type
    {
        return Type::getStandardTypes()[$nodeName]
            ?? $this->typeRegistry->get($nodeName);
    }
}
