<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Nuwave\Lighthouse\Subscriptions\Routes;
use Illuminate\Container\Container as Application;
use Nuwave\Lighthouse\Subscriptions\Iterators\SyncIterator;
use Nuwave\Lighthouse\Subscriptions\Storage\DatabaseStorage;
use Nuwave\Lighthouse\Schema\Extensions\SubscriptionExtension;
use Nuwave\Lighthouse\Subscriptions\Contracts\RegistersRoutes;
use Nuwave\Lighthouse\Subscriptions\Contracts\ContextSerializer;
use Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions;
use Nuwave\Lighthouse\Subscriptions\Directives\BroadcastDirective;
use Nuwave\Lighthouse\Subscriptions\Contracts\SubscriptionIterator;
use Nuwave\Lighthouse\Subscriptions\Directives\SubscriptionDirective;
use Nuwave\Lighthouse\Support\Contracts\SubscriptionExceptionHandler;
use Nuwave\Lighthouse\Subscriptions\Contracts\AuthorizesSubscriptions;
use Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions;
use Nuwave\Lighthouse\Subscriptions\Events\BroadcastSubscriptionEvent;
use Nuwave\Lighthouse\Subscriptions\Events\BroadcastSubscriptionListener;

class SubscriptionProvider
{
    /**
     * Register subscription services.
     *
     * @param Application
     */
    public static function register(Application $app)
    {
        $app->singleton(BroadcastManager::class);
        $app->singleton(SubscriptionRegistry::class);
        $app->singleton(StoresSubscriptions::class, StorageManager::class);

        $app->bind(ContextSerializer::class, Serializer::class);
        $app->bind(AuthorizesSubscriptions::class, Authorizer::class);
        $app->bind(SubscriptionIterator::class, SyncIterator::class);
        $app->bind(SubscriptionExceptionHandler::class, ExceptionHandler::class);
        $app->bind(RegistersRoutes::class, Routes::class);
        $app->bind(BroadcastsSubscriptions::class, SubscriptionBroadcaster::class);

        $app->make('events')->listen(
            BroadcastSubscriptionEvent::class,
            BroadcastSubscriptionListener::class
        );
    }

    /**
     * Check if lighthouse subscriptions is enabled.
     *
     * @return bool
     */
    public static function enabled()
    {
        return in_array(SubscriptionExtension::class, config('lighthouse.extensions', []));
    }
}
