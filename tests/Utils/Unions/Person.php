<?php declare(strict_types=1);

namespace Tests\Utils\Unions;

use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\TypeRegistry;

final class Person
{
    public function __construct(
        private TypeRegistry $typeRegistry,
    ) {}

    /** @param  array<string, mixed>  $value */
    public function resolveType(array $value): Type
    {
        $type = isset($value['id'])
            ? 'User'
            : 'Employee';

        return $this->typeRegistry->get($type);
    }
}
