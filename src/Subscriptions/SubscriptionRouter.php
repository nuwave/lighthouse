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
        $router->group(config('lighthouse.subscriptions.route', []), function (): void {
            $routeName = config('lighthouse.route_name', 'graphql');

            $router->post($routeName. '/subscriptions/auth', [
                'as' => 'lighthouse.subscriptions.auth',
                'uses' => SubscriptionController::class.'@authorize',
            ]);

            $router->post($routeName. '/subscriptions/webhook', [
                'as' => 'lighthouse.subscriptions.auth',
                'uses' => SubscriptionController::class.'@webhook',
            ]);

        });
    }
}
