<?php

namespace Nuwave\Relay;

use Illuminate\Support\Arr;

class GraphQL
{
    /**
     * Instance of application.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    /**
     * Registered GraphQL Types.
     *
     * @var array
     */
    protected $types = [];

    /**
     * Create new instance of graphql container.
     *
     * @param \Illuminate\Contracts\Foundation\Application $app
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * Add new type to collection.
     *
     * @param mixed $class
     * @param string|null $name
     * @return void
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
     * Get collection of registered types.
     *
     * @return \Illuminate\Support\Collection
     */
    public function types()
    {
        return $this->getTypes();
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
