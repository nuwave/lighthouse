<?php

app('router')->group(config('lighthouse.route', []), function (): void {
    $routeName = config('lighthouse.route_name', 'graphql');
    $controller = config('lighthouse.controller');

    $methods = config('lighthouse.route_enable_get', false) ?
        ['GET', 'POST'] :
        ['POST'];

    app('router')->match($methods, $routeName, [
        'as' => 'lighthouse.graphql',
        'uses' => $controller,
    ]);
});
