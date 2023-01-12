<?php

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Nuwave\Lighthouse\Support\Http\Controllers\GraphQLController;

$container = Container::getInstance();

$config = $container->make(ConfigRepository::class);
if ($routeConfig = $config->get('lighthouse.route')) {
    /**
     * Not using assert() as only one of those classes will actually be installed.
     *
     * @var \Illuminate\Contracts\Routing\Registrar|\Laravel\Lumen\Routing\Router $router
     */
    $router = $container->make('router');

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

    if (isset($routeConfig['where'])) {
        $actions['where'] = $routeConfig['where'];
    }

    $router->addRoute(
        ['GET', 'POST'],
        $routeConfig['uri'] ?? 'graphql',
        $actions
    );
}
