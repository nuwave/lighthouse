<?php

resolve('router')->group(config('lighthouse.route', []), function () {
    $routeName = config('lighthouse.route_name', 'graphql');
    $controller = config('lighthouse.controller');

    if (config('lighthouse.route_enable_get', false)) {
        resolve('router')->get($routeName, [
            'as' => 'lighthouse.graphql',
            'uses' => $controller,
        ]);
    }

    resolve('router')->post($routeName, [
        'as' => 'lighthouse.graphql',
        'uses' => $controller,
    ]);
});

if ($authController = config('lighthouse.subscriptions.auth.controller')) {
    resolve('router')->group(config('lighthouse.subscriptions.auth.route', []), function () use ($authController) {
        $routeName = config('lighthouse.subscriptions.auth.route_name');

        resolve('router')->post($routeName, [
            'as' => 'lighthouse.subscriptions.auth',
            'uses' => $authController,
        ]);
    });
}

if ($webhookController = config('lighthouse.subscriptions.webhook.controller')) {
    resolve('router')->group(config('lighthouse.subscriptions.auth.route', []), function () use ($webhookController) {
        $routeName = config('lighthouse.subscriptions.webhook.route_name');

        resolve('router')->post($routeName, [
            'as' => 'lighthouse.subscriptions.auth',
            'uses' => $webhookController,
        ]);
    });
}
