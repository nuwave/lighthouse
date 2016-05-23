<?php

namespace Nuwave\Relay\Schema;

use Nuwave\Relay\Schema\Registrars\TypeRegistrar;
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
     * Set local instance of type registrar.
     *
     * @param TypeRegistrar $registrar
     */
    public function setTypeRegistrar(TypeRegistrar $registrar)
    {
        $this->typeRegistrar = $registrar->setSchema($this);
    }

    /**
     * Get instance of type registrar.
     *
     * @return TypeRegistrar
     */
    public function getTypeRegistrar()
    {
        if (!$this->typeRegistrar) {
            $this->typeRegistrar = app(TypeRegistrar::class)->setSchema($this);
        }

        return $this->typeRegistrar;
    }
}
