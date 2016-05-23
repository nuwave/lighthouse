<?php

namespace Nuwave\Relay\Schema;

use Nuwave\Relay\Schema\Registrars\TypeRegistrar;

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
        return $this->typeRegistrar ?: app(TypeRegistrar::class)->setSchema($this);
    }
}
