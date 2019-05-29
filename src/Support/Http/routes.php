<?php

if($routeConfig = config('lighthouse.route')) {
    /** @var \Illuminate\Contracts\Routing\Registrar $router */
    $router = app('router');

    $router->match(
        ['GET', 'POST'],
        $routeConfig['uri'] ?? 'graphql',
        [
            'as' => $routeConfig['name'] ?? 'graphql',
            'uses' => \Nuwave\Lighthouse\Support\Http\Controllers\GraphQLController::class.'@query',
            'middleware' => $routeConfig['middleware'],
        ]
    );
}
