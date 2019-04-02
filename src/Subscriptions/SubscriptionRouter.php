<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Nuwave\Lighthouse\Support\Http\Controllers\SubscriptionController;

class SubscriptionRouter
{
    /**
     * Register the routes for pusher based subscriptions.
     *
     * @param  \Illuminate\Routing\Router  $router
     * @return void
     */
    public function pusher($router): void
    {
        $middleware = config('lighthouse.subscriptions.route.middleware');

        $router->post('graphql/subscriptions/auth', [
            'as' => 'lighthouse.subscriptions.auth',
            'uses' => SubscriptionController::class.'@authorize',
        ])->middleware($middleware);

        $router->post('graphql/subscriptions/webhook', [
            'as' => 'lighthouse.subscriptions.auth',
            'uses' => SubscriptionController::class.'@webhook',
        ])->middleware($middleware);
    }
}
