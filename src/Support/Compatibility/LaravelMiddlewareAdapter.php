<?php

namespace Nuwave\Lighthouse\Support\Compatibility;

use Illuminate\Routing\Router;

class LaravelMiddlewareAdapter implements MiddlewareAdapter
{
    /**
     * @var \Illuminate\Routing\Router
     */
    protected $router;

    /**
     * Create a middleware adapter for Laravel applications.
     *
     * @param  \Illuminate\Routing\Router  $router
     * @return void
     */
    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    /**
     * Get all of the defined middleware short-hand names.
     *
     * @return string[]
     */
    public function getMiddleware(): array
    {
        return $this->router->getMiddleware();
    }

    /**
     * Get all of the defined middleware groups.
     *
     * @return string[]
     */
    public function getMiddlewareGroups(): array
    {
        return $this->router->getMiddlewareGroups();
    }
}
