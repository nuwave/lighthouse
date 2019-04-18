<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Illuminate\Support\Str;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\StartExecution;
use Illuminate\Config\Repository as ConfigRepository;
use Nuwave\Lighthouse\Events\BuildExtensionsResponse;
use Nuwave\Lighthouse\Subscriptions\Iterators\SyncIterator;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Nuwave\Lighthouse\Subscriptions\Contracts\ContextSerializer;
use Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions;
use Nuwave\Lighthouse\Subscriptions\Contracts\SubscriptionIterator;
use Nuwave\Lighthouse\Support\Contracts\ProvidesSubscriptionResolver;
use Nuwave\Lighthouse\Support\Contracts\SubscriptionExceptionHandler;
use Nuwave\Lighthouse\Subscriptions\Contracts\AuthorizesSubscriptions;
use Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions;
use Nuwave\Lighthouse\Subscriptions\Events\BroadcastSubscriptionEvent;
use Nuwave\Lighthouse\Subscriptions\Events\BroadcastSubscriptionListener;

class SubscriptionServiceProvider extends ServiceProvider
{
    /**
     * @param  \Illuminate\Contracts\Events\Dispatcher  $eventsDispatcher
     * @param  \Illuminate\Config\Repository  $configRepository
     * @return void
     */
    public function boot(EventsDispatcher $eventsDispatcher, ConfigRepository $configRepository): void
    {
        $eventsDispatcher->listen(
            BroadcastSubscriptionEvent::class,
            BroadcastSubscriptionListener::class
        );

        $eventsDispatcher->listen(
            StartExecution::class,
            SubscriptionRegistry::class.'@handleStartExecution'
        );

        $eventsDispatcher->listen(
            BuildExtensionsResponse::class,
            SubscriptionRegistry::class.'@handleBuildExtensionsResponse'
        );

        // Register the routes for the configured broadcaster. The specific
        // method that is used can be changed, so we retrieve its name
        // dynamically and then call it with an instance of 'router'.
        $broadcaster = $configRepository->get('lighthouse.subscriptions.broadcaster');
        if ($routesMethod = $configRepository->get("lighthouse.subscriptions.broadcasters.{$broadcaster}.routes")) {
            [$router, $method] = Str::parseCallback($routesMethod, 'pusher');

            call_user_func(
                [$this->app->make($router), $method],
                $this->app->make('router')
            );
        }
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
        $this->app->bind(ProvidesSubscriptionResolver::class, SubscriptionResolverProvider::class);
    }
}
