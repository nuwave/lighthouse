<?php

namespace Nuwave\Lighthouse\Execution\Utils;

use Nuwave\Lighthouse\Subscriptions\SubscriptionRegistry;
use Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions;
use Nuwave\Lighthouse\Support\Contracts\SubscriptionExceptionHandler as ExceptionHandler;

class Subscription
{
    /**
     * Broadcast subscription to client(s).
     *
     * @param string $subscriptionField
     * @param string $root
     * @param bool|null
     *
     * @throws \InvalidArgumentException
     */
    public static function broadcast(string $subscriptionField, $root, $queue = null)
    {
        // Ensure we have a schema and registered subscription fields
        // in the event we are calling this method in code.
        app('graphql')->prepSchema();

        /** @var SubscriptionRegistry $registry */
        $registry = app(SubscriptionRegistry::class);
        /** @var BroadcastsSubscriptions $broadcaster */
        $broadcaster = app(BroadcastsSubscriptions::class);
        /** @var ExceptionHandler $exceptionHandler */
        $exceptionHandler = app(ExceptionHandler::class);

        if (! $registry->has($subscriptionField)) {
            throw new \InvalidArgumentException("No subscription field registered for {$subscriptionField}");
        }

        $queue = null === $queue ? config('lighthouse.subscriptions.queue_broadcasts', false) : $queue;
        $method = $queue ? 'queueBroadcast' : 'broadcast';
        $subscription = $registry->subscription($subscriptionField);

        try {
            call_user_func(
                [$broadcaster, $method],
                $subscription,
                $subscriptionField,
                $root
            );
        } catch (\Throwable $e) {
            $exceptionHandler->handleBroadcastError($e);
        }
    }
}
