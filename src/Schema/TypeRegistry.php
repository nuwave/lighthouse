<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Type\Definition\Type;

/**
 * Store the executable types of our GraphQL schema.
 *
 * Class TypeRegistry
 * @package Nuwave\Lighthouse\Schema
 */
class TypeRegistry
{
    /**
     * A map of executable schema types.
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
     * @return Type|null
     */
    public function get(string $typeName)
    {
        return array_get($this->types, $typeName);
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
