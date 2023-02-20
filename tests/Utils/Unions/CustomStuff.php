<?php

namespace Tests\Utils\Unions;

use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\TypeRegistry;

final class CustomStuff
{
    /**
     * @var \Nuwave\Lighthouse\Schema\TypeRegistry
     */
    private $typeRegistry;

    public function __construct(TypeRegistry $typeRegistry)
    {
        $this->typeRegistry = $typeRegistry;
    }

    /**
     * Decide which GraphQL type a resolved value has.
     *
     * @param  mixed  $rootValue  The value that was resolved by the field. Usually an Eloquent model.
     */
    public function resolveType($rootValue): Type
    {
        return $this->typeRegistry->get(
            // Add prefix
            'Custom' . class_basename($rootValue)
        );
    }
}
