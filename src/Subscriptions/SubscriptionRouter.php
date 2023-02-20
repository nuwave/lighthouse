<?php

namespace Nuwave\Lighthouse\Subscriptions;

class SubscriptionRouter
{
    /**
     * Register the routes for pusher based subscriptions.
     *
     * @param  \Illuminate\Contracts\Routing\Registrar|\Laravel\Lumen\Routing\Router  $router
     */
    public function pusher($router): void
    {
        $router->post('graphql/subscriptions/auth', [
            'as' => 'lighthouse.subscriptions.auth',
            'uses' => SubscriptionController::class . '@authorize',
        ]);

        $router->post('graphql/subscriptions/webhook', [
            'as' => 'lighthouse.subscriptions.webhook',
            'uses' => SubscriptionController::class . '@webhook',
        ]);
    }

    /**
     * @param  \Illuminate\Contracts\Routing\Registrar|\Laravel\Lumen\Routing\Router  $router
     */
    public function echoRoutes($router): void
    {
        $router->post('graphql/subscriptions/auth', [
            'as' => 'lighthouse.subscriptions.auth',
            'uses' => SubscriptionController::class . '@authorize',
        ]);
    }
}
