<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Events\StartExecution;
use Nuwave\Lighthouse\Events\StartRequest;
use Nuwave\Lighthouse\Subscriptions\Iterators\SyncIterator;
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
    public function boot(EventDispatcher $eventDispatcher): void
    {
        $eventDispatcher->listen(
            BroadcastSubscriptionEvent::class,
            BroadcastSubscriptionListener::class
        );

        $eventDispatcher->listen(
            StartExecution::class,
            SubscriptionRegistry::class . '@handleStartExecution'
        );

        // Register the routes for the configured broadcaster. The specific
        // method that is used can be changed, so we retrieve its name
        // dynamically and then call it with an instance of 'router'.
        $broadcaster = config('lighthouse.subscriptions.broadcaster');
        $routesMethod = config("lighthouse.subscriptions.broadcasters.{$broadcaster}.routes");

        [$router, $method] = Str::parseCallback($routesMethod, 'pusher');

        call_user_func(
            [app($router), $method],
            app('router')
        );
    }

    /**
     * Register subscription services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(BroadcastManager::class);
        $this->app->singleton(SubscriptionRegistry::class);
        $this->app->singleton(StoresSubscriptions::class, StorageManager::class);

        $this->app->bind(ContextSerializer::class, Serializer::class);
        $this->app->bind(AuthorizesSubscriptions::class, Authorizer::class);
        $this->app->bind(SubscriptionIterator::class, SyncIterator::class);
        $this->app->bind(SubscriptionExceptionHandler::class, ExceptionHandler::class);
        $this->app->bind(BroadcastsSubscriptions::class, SubscriptionBroadcaster::class);
    }
}
