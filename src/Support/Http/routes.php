<?php

use Illuminate\Container\Container;
use Nuwave\Lighthouse\Support\Http\Controllers\GraphQLController;

if ($routeConfig = config('lighthouse.route')) {
    /**
     * Not using assert() as only one of those classes will actually be installed.
     *
     * @var \Illuminate\Contracts\Routing\Registrar|\Laravel\Lumen\Routing\Router $router
     */
    $router = Container::getInstance()->make('router');

    $actions = [
        'as' => $routeConfig['name'] ?? 'graphql',
        'uses' => GraphQLController::class,
    ];

    if (isset($routeConfig['middleware'])) {
        $actions['middleware'] = $routeConfig['middleware'];
    }

    if (isset($routeConfig['prefix'])) {
        $actions['prefix'] = $routeConfig['prefix'];
    }

    if (isset($routeConfig['domain'])) {
        $actions['domain'] = $routeConfig['domain'];
    }

    $router->addRoute(
        ['GET', 'POST'],
        $routeConfig['uri'] ?? 'graphql',
        $actions
    );
}
