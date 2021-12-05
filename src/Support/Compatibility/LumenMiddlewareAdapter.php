<?php

namespace Nuwave\Lighthouse\Support\Compatibility;

use Laravel\Lumen\Application;
use Nuwave\Lighthouse\Support\Utils;

class LumenMiddlewareAdapter implements MiddlewareAdapter
{
    /**
     * @var \Laravel\Lumen\Application
     */
    protected $app;

    public function __construct()
    {
        $this->app = Application::getInstance();
    }

    public function getMiddleware(): array
    {
        // TODO remove once Lumen gains public access to the middleware/routeMiddleware
        $globalMiddleware = Utils::accessProtected($this->app, 'middleware', []);
        $routeMiddleware = Utils::accessProtected($this->app, 'routeMiddleware', []);

        return array_merge($globalMiddleware, $routeMiddleware);
    }

    public function getMiddlewareGroups(): array
    {
        // Lumen doesn't have middleware groups
        return [];
    }
}
