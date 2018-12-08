<?php

namespace Tests\Utils\Interfaces;

use GraphQL\Type\Definition\ObjectType;
use Tests\Utils\Models\Team;
use Tests\Utils\Models\User;
use Nuwave\Lighthouse\Schema\TypeRegistry;

class Nameable
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

    public function resolve($value): ObjectType
    {
        if ($value instanceof User) {
            return $this->typeRegistry->get('User');
        } elseif($value instanceof Team){
            return $this->typeRegistry->get('Team');
        }
    }
}
