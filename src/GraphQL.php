<?php

namespace Nuwave\Relay;

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
     */
    public function addType($class, $name = null)
    {
        return 'foo';
    }
}
