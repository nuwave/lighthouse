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

    public function __construct()
    {
        $this->types = new Collection();
    }

    /**
     * Resolve type instance by name.
     *
     * @param string $typeName
     *
     * @return Type
     */
    public function get($typeName)
    {
        return $this->types->get($typeName);
    }

    /**
     * @param Type $type
     */
    public function register(Type $type)
    {
        $this->types->put($type->name, $type);
    }
}
