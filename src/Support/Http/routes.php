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

/** @var \Nuwave\Lighthouse\Subscriptions\Contracts\RegistersRoutes $subscriptionRoutes */
$subscriptionRoutes = app(\Nuwave\Lighthouse\Subscriptions\Contracts\RegistersRoutes::class);

if ($subscriptionRoutes->authController()) {
    resolve('router')->group(
        $subscriptionRoutes->authGroup(),
        function () use ($subscriptionRoutes) {
            resolve('router')->post($subscriptionRoutes->authRoute(), [
                'as' => 'lighthouse.subscriptions.auth',
                'uses' => $subscriptionRoutes->authController(),
            ]);
        }
    );
}

if ($subscriptionRoutes->webhookController()) {
    resolve('router')->group(
        $subscriptionRoutes->webhookGroup(),
        function () use ($subscriptionRoutes) {
            resolve('router')->post($subscriptionRoutes->webhookRoute(), [
                'as' => 'lighthouse.subscriptions.auth',
                'uses' => $subscriptionRoutes->webhookController(),
            ]);
        }
    );
}
