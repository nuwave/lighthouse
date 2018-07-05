<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Type\Definition\Type;
use Illuminate\Support\Collection;

class TypeRegistry
{
    /**
     * Collection of schema types.
     *
     * @var Collection
     */
    protected $types;

    /**
     * TypeRegistry constructor.
     */
    public function __construct()
    {
        $this->types = collect();
    }

    /**
     * Resolve type instance by name.
     *
     * @param string $typeName
     *
     * @return Type
     */
    public function get(string $typeName): Type
    {
        return $this->types->get($typeName);
    }

    /**
     * Register type with registry.
     *
     * @param Type $type
     */
    public function register(Type $type)
    {
        $this->types->put($type->name, $type);
    }

    /**
     * Resolve type instance by name.
     *
     * @param string $typeName
     *
     * @return Type
     * @deprecated in favour of get()
     */
    public function instance(string $typeName): Type
    {
        return $this->get($typeName);
    }

    /**
     * Register type with registry.
     *
     * @param Type $type
     * @deprecated in favour of register
     */
    public function type(Type $type)
    {
        $this->register($type);
    }
}
