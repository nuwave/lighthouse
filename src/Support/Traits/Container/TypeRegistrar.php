<?php

namespace Nuwave\Relay\Support\Traits\Container;

trait TypeRegistrar
{
    /**
     * Registered GraphQL Types.
     *
     * @var array
     */
    protected $types = [];

    /**
     * Add new type to collection.
     *
     * @param mixed $class
     * @param string|null $name
     * @return boolean
     */
    public function addType($class, $name = null)
    {
        if (!$name) {
            $type = is_object($class) ? $class : app($class);
            $name = $type->name;
        }

        $this->types = array_merge($this->types, [
            $name => $class
        ]);

        return true;
    }

    /**
     * Add new type to collection.
     *
     * @param mixed $class
     * @param string|null $name
     * @return void
     */
    public function type($class, $name = null)
    {
        return $this->addType($class, $name);
    }

    /**
     * Get registered type.
     *
     * @return mixed
     */
    public function getType($type)
    {
        return $this->getTypes()->get($type);
    }

    /**
     * Get collection of types.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getTypes()
    {
        return collect($this->types);
    }
}
