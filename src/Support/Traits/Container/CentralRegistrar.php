<?php

namespace Nuwave\Lighthouse\Support\Traits\Container;

use Nuwave\Lighthouse\Schema\Registrars\TypeRegistrar;
use Nuwave\Lighthouse\Schema\Registrars\EdgeRegistrar;
use Nuwave\Lighthouse\Schema\Registrars\QueryRegistrar;
use Nuwave\Lighthouse\Schema\Registrars\CursorRegistrar;
use Nuwave\Lighthouse\Schema\Registrars\MutationRegistrar;
use Nuwave\Lighthouse\Schema\Registrars\ConnectionRegistrar;
use Nuwave\Lighthouse\Schema\Registrars\DataLoaderRegistrar;

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
     * Mutation registrar.
     *
     * @var MutationRegistrar
     */
    protected $mutationRegistrar;

    /**
     * Cursor registrar.
     *
     * @var CursorRegistrar
     */
    protected $cursorRegistrar;

    /**
     * Data Loader registrar.
     *
     * @var DataLoaderRegistrar
     */
    protected $loaderRegistrar;

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
     * @return Nuwave\Lighthouse\Schema\Registrars\EdgeRegistrar
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
     * @return Nuwave\Lighthouse\Schema\Registrars\ConnectionRegistrar
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

    /**
     * Set local instance of mutation registrar.
     *
     * @param MutationRegistrar $registrar
     */
    public function setMutationRegistrar(MutationRegistrar $registrar)
    {
        $this->mutationRegistrar = $registrar->setSchema($this);
    }

    /**
     * Get instance of mutation registrar.
     *
     * @return MutationRegistrar
     */
    public function getMutationRegistrar()
    {
        if (! $this->mutationRegistrar) {
            $this->mutationRegistrar = app(MutationRegistrar::class)->setSchema($this);
        }

        return $this->mutationRegistrar;
    }

    /**
     * Set local instance of cursor registrar.
     *
     * @param CursorRegistrar $registrar
     */
    public function setCursorRegistrar(CursorRegistrar $registrar)
    {
        $this->cursorRegistrar = $registrar;
    }

    /**
     * Get instance of cursor registrar.
     *
     * @return CursorRegistrar
     */
    public function getCursorRegistrar()
    {
        if (! $this->cursorRegistrar) {
            $this->cursorRegistrar = app(CursorRegistrar::class);
        }

        return $this->cursorRegistrar;
    }

    /**
     * Get instance of Data Loader registrar.
     *
     * @return DataLoaderRegistrar
     */
    public function getDataLoaderRegistrar()
    {
        return app(DataLoaderRegistrar::class);
    }
}
