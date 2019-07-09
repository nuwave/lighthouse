<?php

if ($routeConfig = config('lighthouse.route')) {
    /** @var \Illuminate\Routing\Router|\Laravel\Lumen\Routing\Router $router */
    $router = app('router');

    $router->addRoute(
        ['GET', 'POST'],
        $routeConfig['uri'] ?? 'graphql',
        [
            'as' => $routeConfig['name'] ?? 'graphql',
            'uses' => \Nuwave\Lighthouse\Support\Http\Controllers\GraphQLController::class.'@query',
            'middleware' => $routeConfig['middleware'],
        ]
    );
}
