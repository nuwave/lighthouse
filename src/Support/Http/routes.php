<?php

/** @var \Illuminate\Contracts\Routing\Registrar $router */
$router = app('router');

$router->group(config('lighthouse.route', []), function () use ($router): void {
    $routeName = config('lighthouse.route_name', 'graphql');
    $controller = config('lighthouse.controller');

    $methods = config('lighthouse.route_enable_get', false)
        ? ['GET', 'POST']
        : ['POST'];

    $router->match($methods, $routeName, [
        'as' => 'lighthouse.graphql',
        'uses' => $controller,
    ]);
});
