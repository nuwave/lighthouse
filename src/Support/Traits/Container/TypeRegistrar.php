<?php

namespace Nuwave\Relay\Support\Traits\Container;

use GraphQL\Type\Definition\InterfaceType;
use Illuminate\Database\Eloquent\Model;

trait TypeRegistrar
{
    /**
     * Registered GraphQL Types.
     *
     * @var array
     */
    protected $types = [];

    /**
     * Registered type instances.
     *
     * @var array
     */
    protected $typeInstances = [];

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
     * Register new type instance.
     *
     * @param string $name
     * @param mixed $instance
     */
    protected function addTypeInstance($name, $instance)
    {
        $this->typeInstances = array_merge($this->typeInstances, [
            $name => $instance
        ]);

        return true;
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

    /**
     * get collection of type instances.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getTypeInstances()
    {
        return collect($this->typeInstances);
    }

    /**
     * Get instance of type.
     *
     * @param  string $name
     * @param  boolean $fresh
     * @return mixed
     */
    public function type($name, $fresh = false)
    {
        $this->checkType($name);

        $instances = $this->getTypeInstances();

        if (!$fresh && $instances->has($name)) {
            return $instances->get($name);
        }

        $type = $this->getType($name);

        if (!is_object($type)) {
            $type = app($type);
        }

        $instance = $type instanceof Model ? (new EloquentType($type, $name))->toType() : $type->toType();

        $this->addTypeInstance($name, $instance);

        if ($type->interfaces) {
            InterfaceType::addImplementationToInterfaces($instance);
        }

        return $instance;
    }

    /**
     * Check if type is registered.
     *
     * @param  string $name
     * @return void
     */
    protected function checkType($name)
    {
        if (!$this->getType($name)) {
            throw new \Exception("Type [{$name}] not found.");
        }
    }
}
