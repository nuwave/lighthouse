<?php

namespace Tests\Utils\Unions;

use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\TypeRegistry;

class Person
{
    /**
     * @var \Nuwave\Lighthouse\Schema\TypeRegistry
     */
    protected $typeRegistry;

    public function __construct(TypeRegistry $typeRegistry)
    {
        $this->typeRegistry = $typeRegistry;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    public function resolveType(array $value): Type
    {
        $type = isset($value['id'])
            ? 'User'
            : 'Employee';

        return $this->typeRegistry->get($type);
    }
}
