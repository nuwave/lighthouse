<?php declare(strict_types=1);

namespace Tests\Utils\Unions;

use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\TypeRegistry;

final class CustomStuff
{
    public function __construct(private TypeRegistry $typeRegistry) {}

    /**
     * Decide which GraphQL type a resolved value has.
     *
     * @param  mixed  $rootValue  The value that was resolved by the field. Usually an Eloquent model.
     */
    public function resolveType(mixed $rootValue): Type
    {
        return $this->typeRegistry->get(
            // Add prefix
            'Custom' . class_basename($rootValue)
        );
    }
}
