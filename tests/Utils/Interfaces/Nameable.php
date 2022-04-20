<?php

namespace Tests\Utils\Interfaces;

use GraphQL\Type\Definition\Type;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Tests\Utils\Models\Team;
use Tests\Utils\Models\User;

final class Nameable
{
    /**
     * @var \Nuwave\Lighthouse\Schema\TypeRegistry
     */
    private $typeRegistry;

    public function __construct(TypeRegistry $typeRegistry)
    {
        $this->typeRegistry = $typeRegistry;
    }

    public function resolve(?Model $value): ?Type
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
