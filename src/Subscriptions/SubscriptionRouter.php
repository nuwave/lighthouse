<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Subscriptions;

use Illuminate\Contracts\Routing\Registrar;
use Laravel\Lumen\Routing\Router;

class SubscriptionRouter
{
    /** Register the routes for pusher based subscriptions. */
    public function pusher(Registrar|Router $router): void
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

    public function echoRoutes(Registrar|Router $router): void
    {
        $router->post('graphql/subscriptions/auth', [
            'as' => 'lighthouse.subscriptions.auth',
            'uses' => SubscriptionController::class . '@authorize',
        ]);
    }
}
