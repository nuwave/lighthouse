<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Subscriptions;

use Illuminate\Contracts\Routing\Registrar;
use Laravel\Lumen\Routing\Router;

class SubscriptionRouter
{
    /** Register the routes for Pusher based subscriptions. */
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

    /** Register the routes for Laravel Reverb based subscriptions. */
    public function reverb(Registrar|Router $router): void
    {
        $router->post('graphql/subscriptions/auth', [
            'as' => 'lighthouse.subscriptions.auth',
            'uses' => SubscriptionController::class . '@authorize',
        ]);
    }

    /** Register the routes for Laravel Echo based subscriptions. */
    public function echoRoutes(Registrar|Router $router): void
    {
        $router->post('graphql/subscriptions/auth', [
            'as' => 'lighthouse.subscriptions.auth',
            'uses' => SubscriptionController::class . '@authorize',
        ]);
    }
}
