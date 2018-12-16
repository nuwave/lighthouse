<?php

/** @var \Illuminate\Routing\Router|\Laravel\Lumen\Routing\Router $router */
$router = app('router');

$router->group(config('lighthouse.route', []), function () use ($router) {
    $routeName = config('lighthouse.route_name', 'graphql');
    $controller = config('lighthouse.controller');

    if (config('lighthouse.route_enable_get', false)) {
        $router->get($routeName, [
            'as' => 'lighthouse.graphql',
            'uses' => $controller,
        ]);
    }

    $router->post($routeName, [
        'as' => 'lighthouse.graphql',
        'uses' => $controller,
    ]);
});

if (\Nuwave\Lighthouse\SubscriptionServiceProvider::enabled()) {
    \Nuwave\Lighthouse\SubscriptionServiceProvider::registerRoutes($router);
}
