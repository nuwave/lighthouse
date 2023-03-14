<?php declare(strict_types=1);

namespace Tests\Utils\Unions;

use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\TypeRegistry;

final class CustomStuff
{
    public function __construct(
        private TypeRegistry $typeRegistry,
    ) {}

    public function resolveType(mixed $root): Type
    {
        $classBasename = class_basename($root);

        return $this->typeRegistry->get("Custom{$classBasename}");
    }
}
