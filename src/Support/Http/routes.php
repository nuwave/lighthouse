<?php

use Illuminate\Support\Str;

if ($routeConfig = config('lighthouse.route')) {
    /** @var \Illuminate\Contracts\Routing\Registrar $router */
    $router = app('router');

    $router->group(config('lighthouse.route', []), function () use ($router, $routeConfig): void {
        $method = 'addRoute';
        if (Str::startsWith(app()->version(), '5.5.')) {
            $method = 'match';
        }

        $router->$method(
            ['GET', 'POST'],
            $routeConfig['uri'] ?? 'graphql',
            [
                'as' => $routeConfig['name'] ?? 'graphql',
                'uses' => \Nuwave\Lighthouse\Support\Http\Controllers\GraphQLController::class.'@query',
                'middleware' => $routeConfig['middleware'],
            ]
        );
    });

}