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

    public function resolveType($value): Type
    {
        // The return type can be a string either,
        // because the upstream lib `webonyx/graphql-php` allows us to give a string
        // which in this case you can just return the `$type` it self.
        $type = isset($value['id'])
            ? 'User'
            : 'Employee';

        return $this->typeRegistry->get($type);
    }
}
