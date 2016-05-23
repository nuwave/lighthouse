<?php

namespace Nuwave\Relay\Schema;

use Nuwave\Relay\Support\Traits\Container\CentralRegistrar;
use GraphQL\Type\Definition\ObjectType;

class SchemaBuilder
{
    use CentralRegistrar;

    /**
     * Current namespace.
     *
     * @var array
     */
    protected $namespace = '';

    /**
     * Get current namespace.
     *
     * @return string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * Set current namespace.
     *
     * @param string $namespace
     */
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
    }

    /**
     * Add query to registrar.
     *
     * @param  string $name
     * @param  string $namespace
     * @return \Nuwave\Relay\Schema\Field
     */
    public function query($name, $namespace)
    {
        return $this->getQueryRegistrar()->register($name, $namespace);
    }

    /**
     * Add mutation to registrar.
     *
     * @param  string $name
     * @param  string $namespace
     * @return \Nuwave\Relay\Schema\Field
     */
    public function mutation($name, $namespace)
    {
        return $this->getMutationRegistrar()->register($name, $namespace);
    }

    /**
     * Add type to registrar.
     *
     * @param  string $name
     * @param  string $namespace
     * @return \Nuwave\Relay\Schema\Field
     */
    public function type($name, $namespace)
    {
        return $this->getTypeRegistrar()->register($name, $namespace);
    }

    /**
     * Add connection to registrar.
     *
     * @param  string $name
     * @param  array $field
     * @return array
     */
    public function connection($name, $field)
    {
        return $this->getConnectionRegistrar()->register($name, $field);
    }

    /**
     * Get type field from registrar.
     *
     * @param  string $name
     * @return \Nuwave\Relay\Schema\Field
     */
    public function getTypeField($name)
    {
        return $this->getTypeRegistrar()->get($name);
    }

    /**
     * Extract type instance from registrar.
     *
     * @param  string $name
     * @param  boolean $fresh
     * @return ObjectType
     */
    public function typeInstance($name, $fresh = false)
    {
        return $this->getTypeRegistrar()->instance($name, $fresh);
    }

    /**
     * Extract connection instance from registrar.
     *
     * @param  string $name
     * @param  Closure|null $resolve
     * @param  boolean $fresh
     * @return ObjectType
     */
    public function connectionInstance($name, Closure $resolve = null, $fresh = false)
    {
        return $this->getConnectionRegistrar()->instance($name, $resolve, $fresh);
    }

    /**
     * Extract edge instance from registrar.
     *
     * @param  string $name
     * @param  ObjectType|null $type
     * @param  boolean $fresh
     * @return ObjectType
     */
    public function edgeInstance($name, $type = null, $fresh = false)
    {
        return $this->getEdgeRegistrar()->instance($name, $fresh, $type);
    }
}
