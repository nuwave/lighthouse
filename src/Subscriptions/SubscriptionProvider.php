<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Illuminate\Contracts\Foundation\Application;
use Nuwave\Lighthouse\Subscriptions\Iterators\SyncIterator;
use Nuwave\Lighthouse\Subscriptions\Storage\DatabaseStorage;
use Nuwave\Lighthouse\Schema\Extensions\SubscriptionExtension;
use Nuwave\Lighthouse\Subscriptions\Contracts\ContextSerializer;
use Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions;
use Nuwave\Lighthouse\Subscriptions\Directives\BroadcastDirective;
use Nuwave\Lighthouse\Subscriptions\Contracts\SubscriptionIterator;
use Nuwave\Lighthouse\Subscriptions\Directives\SubscriptionDirective;
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
        $app->bind(ContextSerializer::class, Serializer::class);
        $app->bind(StoresSubscriptions::class, DatabaseStorage::class);
        $app->bind(AuthorizesSubscriptions::class, Authorizer::class);
        $app->bind(SubscriptionIterator::class, SyncIterator::class);

        $app->singleton(SubscriptionRegistry::class);
        $app->singleton(BroadcastsSubscriptions::class, function ($app) {
            $appKey = env('PUSHER_APP_KEY');
            $appSecret = env('PUSHER_APP_SECRET');
            $appId = env('PUSHER_APP_ID');
            $appCluster = env('PUSHER_APP_CLUSTER');
            $appEncryption = env('PUSHER_MASTER_KEY');

            $auth = $app->get(AuthorizesSubscriptions::class);
            $storage = $app->get(StoresSubscriptions::class);
            $iterator = $app->get(SubscriptionIterator::class);
            $pusher = new \Pusher\Pusher($appKey, $appSecret, $appId, [
                'cluster' => $appCluster,
                'encryption_master_key' => $appEncryption,
            ]);

            return new Broadcaster($auth, $pusher, $storage, $iterator);
        });

        $app->get('events')->listen(
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
