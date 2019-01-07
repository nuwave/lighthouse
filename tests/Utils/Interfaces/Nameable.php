<?php

namespace Tests\Utils\Interfaces;

use Tests\Utils\Models\Team;
use Tests\Utils\Models\User;
use GraphQL\Type\Definition\ObjectType;
use Nuwave\Lighthouse\Schema\TypeRegistry;

class Nameable
{
    /**
     * @var \Nuwave\Lighthouse\Schema\TypeRegistry
     */
    protected $typeRegistry;

    /**
     * @param  \Nuwave\Lighthouse\Schema\TypeRegistry  $typeRegistry
     */
    public function __construct(TypeRegistry $typeRegistry)
    {
        $this->typeRegistry = $typeRegistry;
    }

    public function resolve($value): ?ObjectType
    {
        if ($value instanceof User) {
            return $this->typeRegistry->get('User');
        }

        if ($value instanceof Team) {
            return $this->typeRegistry->get('Team');
        }

        return null;
    }
}
