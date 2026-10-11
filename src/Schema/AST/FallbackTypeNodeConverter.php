<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\AST;

use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\TypeRegistry;

class FallbackTypeNodeConverter extends TypeNodeConverter
{
    public function __construct(
        protected TypeRegistry $typeRegistry,
    ) {}

    protected function nonNull(mixed $type): NonNull
    {
        return Type::nonNull($type);
    }

    /**
     * @template T of Type
     *
     * @param  T|callable():T  $type
     *
     * @return ListOfType<T>
     */
    protected function listOf(mixed $type): ListOfType
    {
        return Type::listOf($type);
    }

    protected function namedType(string $nodeName): Type
    {
        $standardTypes = Type::getStandardTypes();
        if (isset($standardTypes[$nodeName])) {
            return $standardTypes[$nodeName];
        }

        if (! $this->typeRegistry->has($nodeName)) {
            $dummyType = new CustomScalarType([
                'name' => $nodeName,
                'serialize' => static function (): void {},
            ]);
            $this->typeRegistry->register($dummyType);

            return $dummyType;
        }

        return $this->typeRegistry->get($nodeName);
    }
}
