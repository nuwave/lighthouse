<?php

namespace Nuwave\Lighthouse\Schema;

use Closure;
use GraphQL\Type\Definition\ObjectType;
use Nuwave\Lighthouse\Support\Traits\Container\CentralRegistrar;

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
     * Schema middleware stack.
     *
     * @var array
     */
    protected $middlewareStack = [];

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
     * Group child elements.
     *
     * @param  array   $attributes
     * @param  Closure $callback
     * @return void
     */
    public function group(array $attributes, Closure $callback)
    {
        $oldNamespace = $this->getNamespace();

        if (isset($attributes['middleware'])) {
            $this->middlewareStack[] = $attributes['middleware'];
        }

        if (isset($attributes['namespace'])) {
            $this->namespace .= '\\'.trim($attributes['namespace'], '\\');
        }

        $callback();

        if (isset($attributes['middleware'])) {
            array_pop($this->middlewareStack);
        }

        if (isset($attributes['namespace'])) {
            $this->namespace = $oldNamespace;
        }
    }

    /**
     * Get instance of query parser.
     *
     * @param  string $query
     * @return self
     */
    public function parse($query = '')
    {
        return new QueryParser($this, $query);
    }

    /**
     * Get current middleware stack.
     *
     * @return array
     */
    public function getMiddlewareStack()
    {
        return $this->middlewareStack;
    }

    /**
     * Add query to registrar.
     *
     * @param  string $name
     * @param  string $namespace
     * @return \Nuwave\Lighthouse\Schema\Field
     */
    public function query($name, $namespace)
    {
        return $this->getQueryRegistrar()->register($name, $namespace);
    }

    /**
     * Add queries to registrar.
     *
     * @param  array $queries
     * @return \Nuwave\Lighthouse\Schema\Field[]
     */
    public function queries(array $queries)
    {
        $registered = [];

        foreach ($queries as $name => $namespace) {
            $registered[] = $this->query($name, $namespace);
        }

        return $registered;
    }

    /**
     * Add subscription to registrar.
     *
     * @param  string $name
     * @param  string $namespace
     * @return \Nuwave\Lighthouse\Schema\Field
     */
    public function subscription($name, $namespace)
    {
        return $this->getSubscriptionRegistrar()->register($name, $namespace);
    }

    /**
     * Add subscriptions to registrar.
     *
     * @param  array $subscriptions
     * @return \Nuwave\Lighthouse\Schema\Field[]
     */
    public function subscriptions(array $subscriptions)
    {
        $registered = [];

        foreach ($subscriptions as $name => $namespace) {
            $registered[] = $this->subscription($name, $namespace);
        }

        return $registered;
    }

    /**
     * Add mutation to registrar.
     *
     * @param  string $name
     * @param  string $namespace
     * @return \Nuwave\Lighthouse\Schema\Field
     */
    public function mutation($name, $namespace)
    {
        return $this->getMutationRegistrar()->register($name, $namespace);
    }

    /**
     * Add mutations to registrar.
     *
     * @param  array $mutations
     * @return \Nuwave\Lighthouse\Schema\Field[]
     */
    public function mutations(array $mutations)
    {
        $registered = [];

        foreach ($mutations as $name => $namespace) {
            $registered[] = $this->mutation($name, $namespace);
        }

        return $registered;
    }

    /**
     * Add type to registrar.
     *
     * @param  string $name
     * @param  string $namespace
     * @return \Nuwave\Lighthouse\Schema\Field
     */
    public function type($name, $namespace)
    {
        return $this->getTypeRegistrar()->register($name, $namespace);
    }

    /**
     * Add types to registrar.
     *
     * @param  array $types
     * @return \Nuwave\Lighthouse\Schema\Field[]
     */
    public function types(array $types)
    {
        $registered = [];

        foreach ($types as $name => $namespace) {
            $registered[] = $this->type($name, $namespace);
        }

        return $registered;
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
     * Add connections to registrar.
     *
     * @param  array $connections
     * @return \Nuwave\Lighthouse\Schema\Field[]
     */
    public function connections(array $connections)
    {
        $registered = [];

        foreach ($connections as $name => $namespace) {
            $registered[] = $this->connection($name, $namespace);
        }

        return $registered;
    }

    /**
     * Add cursor to registrar.
     *
     * @param  string  $name
     * @param  Closure $encoder
     * @param  Closure|null $decoder
     * @return bool
     */
    public function cursor($name, Closure $encoder, Closure $decoder = null)
    {
        return $this->getCursorRegistrar()->register($name, $encoder, $decoder);
    }

    /**
     * Add Data fetcher to registrar.
     *
     * @param  string $name
     * @param  string $fetcher
     * @return bool
     */
    public function dataFetcher($name, $fetcher)
    {
        return $this->getDataFetcherRegistrar()->register($name, $fetcher);
    }

    /**
     * Add Data loader to registrar.
     *
     * @param  string $name
     * @param  string $loader
     * @return bool
     */
    public function dataLoader($name, $loader)
    {
        return $this->getDataLoaderRegistrar()->register($name, $loader);
    }

    /**
     * Get type field from registrar.
     *
     * @param  string $name
     * @return \Nuwave\Lighthouse\Schema\Field
     */
    public function getTypeField($name)
    {
        return $this->getTypeRegistrar()->get($name);
    }

    /**
     * Extract type instance from registrar.
     *
     * @param  string $name
     * @param  bool $fresh
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
     * @param  string|null $parent
     * @param  bool $fresh
     * @return ObjectType
     */
    public function connectionInstance($name, $parent = null, $fresh = false)
    {
        return $this->getConnectionRegistrar()->instance($name, $parent, $fresh);
    }

    /**
     * Extract edge instance from registrar.
     *
     * @param  string $name
     * @param  ObjectType|null $type
     * @param  bool $fresh
     * @return ObjectType
     */
    public function edgeInstance($name, $type = null, $fresh = false)
    {
        return $this->getEdgeRegistrar()->instance($name, $fresh, $type);
    }

    /**
     * Extract Data Fetcher instance from registrar.
     *
     * @param  string $name
     * @return \Nuwave\Lighthouse\Support\DataLoader\GraphQLDataFetcher
     */
    public function dataFetcherInstance($name)
    {
        return $this->getDataFetcherRegistrar()->instance($name);
    }

    /**
     * Extract Data Loader instance from registrar.
     *
     * @param  string $name
     * @return \Nuwave\Lighthouse\Support\DataLoader\GraphQLDataLoader
     */
    public function dataLoaderInstance($name)
    {
        return $this->getDataLoaderRegistrar()->instance($name);
    }

    /**
     * Get encoder for connection edge.
     *
     * @param  string $name
     * @return Closure
     */
    public function encoder($name)
    {
        return $this->getCursorRegistrar()->encoder($name);
    }

    /**
     * Get encoder for connection edge.
     *
     * @param  string $name
     * @return Closure
     */
    public function decoder($name)
    {
        return $this->getCursorRegistrar()->decoder($name);
    }
}
