<?php

namespace Tests\Utils\Unions;

use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\TypeRegistry;

class Person
{
    /** @var TypeRegistry */
    protected $typeRegistry;
    
    /**
     * @param TypeRegistry $typeRegistry
     */
    public function __construct(TypeRegistry $typeRegistry)
    {
        $this->typeRegistry = $typeRegistry;
    }
    
    public function resolveType($value, $context, ResolveInfo $info): Type
    {
        // The return type can be a string either,
        // because the upstream lib `webonyx/graphql-php` allows us to give a string
        // which in this case you can just return the `$type` it self.
        $type = isset($value['id']) ? 'User' : 'Employee';

        return $this->typeRegistry->get($type);
    }
}
