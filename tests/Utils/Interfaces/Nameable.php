<?php

namespace Tests\Utils\Interfaces;

use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Tests\Utils\Models\Team;
use Tests\Utils\Models\User;

class Nameable
{
    /**
     * @var \Nuwave\Lighthouse\Schema\TypeRegistry
     */
    protected $typeRegistry;

    public function __construct(TypeRegistry $typeRegistry)
    {
        $this->typeRegistry = $typeRegistry;
    }

    public function resolve($value): ?Type
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
