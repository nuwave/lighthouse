<?php

$route = config('lighthouse.route', []);
$route_name = config('lighthouse.route_name', 'graphql');
$route_enable_get = config('lighthouse.route_enable_get', false);
$controller = config('lighthouse.controller');

app('router')->group($route, function () use ($controller, $route_name, $route_enable_get) {
    if ($route_enable_get) {
        app('router')->get($route_name,
          ['as' => 'lighthouse.graphql', 'uses' => $controller]
        );
    }
    app('router')->post($route_name,
      ['as' => 'lighthouse.graphql', 'uses' => $controller]
    );
});
