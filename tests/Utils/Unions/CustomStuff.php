<?php

namespace Tests\Utils\Unions;

use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\TypeRegistry;

class CustomStuff
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
    
    /**
     * Decide which GraphQL type a resolved value has.
     *
     * @param $rootValue The value that was resolved by the field. Usually an Eloquent model.
     * @param $context
     * @param ResolveInfo $info
     *
     * @return Type
     */
    public function resolveType($rootValue, $context, ResolveInfo $info): Type
    {
        return $this->typeRegistry->get(
            // Add prefix
            'Custom' . class_basename($rootValue)
        );
    }
}
