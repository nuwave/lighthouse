<?php

namespace Nuwave\Lighthouse;

use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Subscriptions\Authorizer;
use Nuwave\Lighthouse\Subscriptions\Serializer;
use Nuwave\Lighthouse\Subscriptions\BroadcastManager;
use Nuwave\Lighthouse\Subscriptions\ExceptionHandler;
use Nuwave\Lighthouse\Subscriptions\SubscriptionStorage;
use Nuwave\Lighthouse\Subscriptions\SubscriptionRegistry;
use Nuwave\Lighthouse\Subscriptions\Iterators\SyncIterator;
use Nuwave\Lighthouse\Subscriptions\SubscriptionBroadcaster;
use Nuwave\Lighthouse\Schema\Extensions\SubscriptionExtension;
use Nuwave\Lighthouse\Subscriptions\Contracts\ContextSerializer;
use Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions;
use Nuwave\Lighthouse\Subscriptions\Contracts\SubscriptionIterator;
use Nuwave\Lighthouse\Support\Contracts\SubscriptionExceptionHandler;
use Nuwave\Lighthouse\Subscriptions\Contracts\AuthorizesSubscriptions;
use Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions;
use Nuwave\Lighthouse\Subscriptions\Events\BroadcastSubscriptionEvent;
use Nuwave\Lighthouse\Subscriptions\Events\BroadcastSubscriptionListener;

class SubscriptionServiceProvider extends ServiceProvider
{
    /**
     * Register subscription services.
     */
    public function register()
    {
        $this->app->singleton(BroadcastManager::class);
        $this->app->singleton(SubscriptionRegistry::class);
        $this->app->singleton(StoresSubscriptions::class, SubscriptionStorage::class);

        $this->app->bind(ContextSerializer::class, Serializer::class);
        $this->app->bind(AuthorizesSubscriptions::class, Authorizer::class);
        $this->app->bind(SubscriptionIterator::class, SyncIterator::class);
        $this->app->bind(SubscriptionExceptionHandler::class, ExceptionHandler::class);
        $this->app->bind(BroadcastsSubscriptions::class, SubscriptionBroadcaster::class);

        $this->app->make('events')->listen(
            BroadcastSubscriptionEvent::class,
            BroadcastSubscriptionListener::class
        );
    }

    /**
     * Generate subscription routes.
     *
     * @param \Illuminate\Routing\Router|\Laravel\Lumen\Routing\Router $router
     */
    public static function registerRoutes($router)
    {
        $broadcaster = config('lighthouse.subscriptions.broadcaster');
        $routesProvider = config("lighthouse.subscriptions.broadcasters.{$broadcaster}.routes");
        $routerParts = explode('@', $routesProvider);

        if (2 === count($routerParts) && ! empty($routerParts[0]) && ! empty($routerParts[1])) {
            $routesProviderInstance = app($routerParts[0]);
            $method = $routerParts[1];

            call_user_func([$routesProviderInstance, $method], $router);
        }
    }

    /**
     * Check if lighthouse subscriptions is enabled.
     *
     * @return bool
     */
    public static function enabled()
    {
        return in_array(
            SubscriptionExtension::class,
            config('lighthouse.extensions', [])
        );
    }
}
