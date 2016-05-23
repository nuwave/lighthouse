<?php

namespace Nuwave\Relay\Support\Traits\Container;

use Nuwave\Relay\Schema\Registrars\TypeRegistrar;
use Nuwave\Relay\Schema\Registrars\EdgeRegistrar;
use Nuwave\Relay\Schema\Registrars\QueryRegistrar;
use Nuwave\Relay\Schema\Registrars\ConnectionRegistrar;

trait CentralRegistrar
{
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
     * Query registrar.
     *
     * @var QueryRegistrar
     */
    protected $queryRegistrar;

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
     * Get instance of connection registrar.
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

    /**
     * Set local instance of query registrar.
     *
     * @param QueryRegistrar $registrar
     */
    public function setQueryRegistrar(QueryRegistrar $registrar)
    {
        $this->queryRegistrar = $registrar->setSchema($this);
    }

    /**
     * Get instance of query registrar.
     *
     * @return QueryRegistrar
     */
    public function getQueryRegistrar()
    {
        if (! $this->queryRegistrar) {
            $this->queryRegistrar = app(QueryRegistrar::class)->setSchema($this);
        }

        return $this->queryRegistrar;
    }
}
