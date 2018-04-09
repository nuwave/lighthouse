<?php

$route = config('lighthouse.route', []);
$controller = config('lighthouse.controller');

app('router')->group($route, function () use ($controller) {
    app('router')->post('graphql', ['as' => 'graphql', 'uses' => $controller]);
});
