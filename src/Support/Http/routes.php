<?php

use Illuminate\Support\Str;

if ($routeConfig = config('lighthouse.route')) {
    /** @var \Illuminate\Contracts\Routing\Registrar|\Laravel\Lumen\Routing\Router $router */
    $router = app('router');

    $method = Str::contains(app()->version(), 'Lumen')
        ? 'addRoute'
        : 'match';

    $router->$method(
        ['GET', 'POST'],
        $routeConfig['uri'] ?? 'graphql',
        [
            'as' => $routeConfig['name'] ?? 'graphql',
            'uses' => \Nuwave\Lighthouse\Support\Http\Controllers\GraphQLController::class.'@query',
            'middleware' => $routeConfig['middleware'],
        ]
    );
}
