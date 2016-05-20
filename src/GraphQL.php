<?php

namespace Nuwave\Relay;

use Nuwave\Relay\Support\Traits\Container\TypeRegistrar;
use Nuwave\Relay\Support\Traits\Container\QueryRegistrar;

class GraphQL
{
    use TypeRegistrar, QueryRegistrar;

    /**
     * Instance of application.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    /**
     * Create new instance of graphql container.
     *
     * @param \Illuminate\Contracts\Foundation\Application $app
     */
    public function __construct($app)
    {
        $this->app = $app;
    }
}
