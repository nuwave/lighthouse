<?php

app('router')->group(config('lighthouse.route', []), function (): void {
    $routeName = config('lighthouse.route_name', 'graphql');
    $controller = config('lighthouse.controller');

    if (config('lighthouse.route_enable_get', false)) {
        app('router')->get($routeName, [
            'as' => 'lighthouse.graphql',
            'uses' => $controller,
        ]);
    }

    app('router')->post($routeName, [
        'as' => 'lighthouse.graphql',
        'uses' => $controller,
    ]);
});
