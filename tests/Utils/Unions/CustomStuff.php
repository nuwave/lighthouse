<?php

namespace Tests\Utils\Unions;

use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\TypeRegistry;

class CustomStuff
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
     * Decide which GraphQL type a resolved value has.
     *
     * @param  mixed  $rootValue The value that was resolved by the field. Usually an Eloquent model.
     * @return \GraphQL\Type\Definition\Type
     */
    public function resolveType($rootValue): Type
    {
        return $this->typeRegistry->get(
            // Add prefix
            'Custom'.class_basename($rootValue)
        );
    }
}
