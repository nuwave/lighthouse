<?php

$route = config('lighthouse.route') ?: [];
$controller = config('lighthouse.controller');

Route::group($route, function () use ($controller) {
    Route::post('graphql', ['as' => 'graphql', 'uses' => $controller]);
});
