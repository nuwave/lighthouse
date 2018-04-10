<?php

$route = config('lighthouse.route', []);
$route_name = config('lighthouse.route_name', 'graphql');
$controller = config('lighthouse.controller');

Route::group($route, function () use ($controller, $route_name) {
    Route::post($route_name, ['as' => 'lighthouse.graphql', 'uses' => $controller]);
});
