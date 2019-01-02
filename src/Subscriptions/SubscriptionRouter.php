<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Nuwave\Lighthouse\Schema\Extensions\ExtensionRegistry;
use Nuwave\Lighthouse\Schema\Extensions\SubscriptionExtension;
use Nuwave\Lighthouse\Support\Http\Controllers\SubscriptionController;

class SubscriptionRouter
{
    /** @var ExtensionRegistry */
    protected $extensions;

    /**
     * @param ExtensionRegistry $extensions
     */
    public function __construct(ExtensionRegistry $extensions)
    {
        $this->extensions = $extensions;
    }

    /**
     * Generate subscription routes.
     */
    public function routes()
    {
        if (! $this->activated()) {
            return;
        }

        $broadcaster = config('lighthouse.subscriptions.broadcaster');
        $router = config("lighthouse.subscriptions.broadcasters.{$broadcaster}.routes");
        $routerParts = explode('@', $router);

        if (count($routerParts) == 2 && ! empty($routerParts[0]) && ! empty($routerParts[1])) {
            $routerInstance = app($routerParts[0]);
            $method = $routerParts[1];

            call_user_func([$routerInstance, $method], app('router'));
        }
    }

    /**
     * Register subscription routes.
     *
     * @param \Illuminate\Routing\Router $router
     */
    public function pusher($router)
    {
        $router->post('graphql/subscriptions/auth', [
            'as' => 'lighthouse.subscriptions.auth',
            'uses' => SubscriptionController::class.'@authorize',
        ]);

        $router->post('graphql/subscriptions/webhook', [
            'as' => 'lighthouse.subscriptions.auth',
            'uses' => SubscriptionController::class.'@webhook',
        ]);
    }

    /**
     * @return bool
     */
    protected function activated()
    {
        return $this->extensions->has(SubscriptionExtension::name());
    }
}
