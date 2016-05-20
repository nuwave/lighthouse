<?php

namespace Nuwave\Relay\Support\Traits\Container;

trait QueryRegistrar
{
    /**
     * Registered GraphQL queries.
     *
     * @var array
     */
    protected $queries = [];

    /**
     * Add new query to collection.
     *
     * @param string $name
     * @param mixed $query
     * @return boolean
     */
    public function addQuery($query, $name)
    {
        $this->queries = array_merge($this->queries, [
            $name => $query
        ]);

        return true;
    }

    /**
     * Get registered query.
     *
     * @param  string $query
     * @return mixed
     */
    public function getQuery($query)
    {
        return $this->getQueries()->get($query);
    }

    /**
     * Get collection of queries.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getQueries()
    {
        return collect($this->queries);
    }
}
