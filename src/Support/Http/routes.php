<?php

use Illuminate\Support\Str;

if ($routeConfig = config('lighthouse.route')) {
    /** @var \Illuminate\Contracts\Routing\Registrar|\Laravel\Lumen\Routing\Router $router */
    $router = app('router');

    $method = 'addRoute';
    if (Str::startsWith(app()->version(), '5.5.')) {
        $method = 'match';
    }

    $actions = [
        'as' => $routeConfig['name'] ?? 'graphql',
        'uses' => \Nuwave\Lighthouse\Support\Http\Controllers\GraphQLController::class.'@query',
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

    $router->$method(
        ['GET', 'POST'],
        $routeConfig['uri'] ?? 'graphql',
        $actions
    );
}
