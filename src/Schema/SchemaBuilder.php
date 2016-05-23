<?php

namespace Nuwave\Relay\Schema;

use Nuwave\Relay\Schema\Registrars\TypeRegistrar;
use Nuwave\Relay\Schema\Registrars\EdgeRegistrar;
use Nuwave\Relay\Schema\Registrars\ConnectionRegistrar;
use GraphQL\Type\Definition\ObjectType;

class SchemaBuilder
{
    /**
     * Current namespace.
     *
     * @var array
     */
    protected $namespace = '';

    /**
     * Type registrar.
     *
     * @var TypeRegistrar
     */
    protected $typeRegistrar;

    /**
     * Edge type registrar.
     *
     * @var EdgeRegistrar
     */
    protected $edgeRegistrar;

    /**
     * Connection type registrar.
     *
     * @var ConnectionRegistrar
     */
    protected $connectionRegistrar;

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

    /**
     * Set local instance of type registrar.
     *
     * @param TypeRegistrar $registrar
     */
    public function setTypeRegistrar(TypeRegistrar $registrar)
    {
        $this->typeRegistrar = $registrar->setSchema($this);
    }

    /**
     * Get instance of edge registrar.
     *
     * @return TypeRegistrar
     */
    public function getTypeRegistrar()
    {
        if (! $this->typeRegistrar) {
            $this->typeRegistrar = app(TypeRegistrar::class)->setSchema($this);
        }

        return $this->typeRegistrar;
    }

    /**
     * Set local instance of edge registrar.
     *
     * @param EdgeRegistrar $registrar
     */
    public function setEdgeRegistrar(EdgeRegistrar $registrar)
    {
        $this->edgeRegistrar = $registrar->setSchema($this);
    }

    /**
     * Get instance of edge registrar.
     *
     * @return Nuwave\Relay\Schema\Registrars\EdgeRegistrar
     */
    public function getEdgeRegistrar()
    {
        if (! $this->edgeRegistrar) {
            $this->edgeRegistrar = app(EdgeRegistrar::class)->setSchema($this);
        }

        return $this->edgeRegistrar;
    }

    /**
     * Set local instance of edge registrar.
     *
     * @param ConnectionRegistrar $registrar
     */
    public function setConnectionRegistrar(ConnectionRegistrar $registrar)
    {
        $this->connectionRegistrar = $registrar->setSchema($this);
    }

    /**
     * Get instance of edge registrar.
     *
     * @return Nuwave\Relay\Schema\Registrars\ConnectionRegistrar
     */
    public function getConnectionRegistrar()
    {
        if (! $this->connectionRegistrar) {
            $this->connectionRegistrar = app(ConnectionRegistrar::class)->setSchema($this);
        }

        return $this->connectionRegistrar;
    }
}
