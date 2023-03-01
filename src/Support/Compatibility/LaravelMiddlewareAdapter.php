<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Support\Compatibility;

use Illuminate\Routing\Router;

class LaravelMiddlewareAdapter implements MiddlewareAdapter
{
    protected Router $router;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    public function getMiddleware(): array
    {
        return $this->router->getMiddleware();
    }

    public function getMiddlewareGroups(): array
    {
        return $this->router->getMiddlewareGroups();
    }
}
