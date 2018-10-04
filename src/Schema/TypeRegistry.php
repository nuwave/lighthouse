<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Type\Definition\Type;
use GraphQL\Error\InvariantViolation;

/**
 * Store the executable types of our GraphQL schema.
 *
 * Class TypeRegistry
 * @package Nuwave\Lighthouse\Schema
 */
class TypeRegistry
{
    /**
     * A map of executable schema types by name.
     *
     * [$typeName => Type]
     *
     * @var Type[]
     */
    protected $types;
    
    /**
     * Register type with registry.
     *
     * @param Type $type
     *
     * @return TypeRegistry
     */
    public function register(Type $type): TypeRegistry
    {
        $this->types[$type->name] = $type;
        
        return $this;
    }

    /**
     * Resolve type instance by name.
     *
     * @param string $typeName
     *
     * @throws InvariantViolation
     *
     * @return Type
     */
    public function get(string $typeName): Type
    {
        if(!isset($this->types[$typeName])){
            throw new InvariantViolation("No type {$typeName} was registered.");
        }

        return $this->types[$typeName];
    }

    /**
     * Resolve type instance by name.
     *
     * @param string $typeName
     *
     * @return Type
     * @deprecated in favour of get, remove in v3
     */
    public function instance($typeName)
    {
        return $this->get($typeName);
    }

    /**
     * Register type with registry.
     *
     * @param Type $type
     * @deprecated in favour of register, remove in v3
     */
    public function type(Type $type)
    {
        $this->register($type);
    }
}
