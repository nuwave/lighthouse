<?php

namespace Nuwave\Lighthouse\Execution\Utils;

use Nuwave\Lighthouse\GraphQL;
use Nuwave\Lighthouse\Subscriptions\SubscriptionRegistry;
use Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions;
use Nuwave\Lighthouse\Support\Contracts\SubscriptionExceptionHandler as ExceptionHandler;

class Subscription
{
    /**
     * Broadcast subscription to client(s).
     *
     * @param string    $subscriptionField
     * @param mixed     $root
     * @param bool|null $shouldQueue
     *
     * @throws \InvalidArgumentException
     */
    public static function broadcast(string $subscriptionField, $root, bool $shouldQueue = null)
    {
        // Ensure we have a schema and registered subscription fields
        // in the event we are calling this method in code.
        app(GraphQL::class)->prepSchema();

        /** @var SubscriptionRegistry $registry */
        $registry = app(SubscriptionRegistry::class);
        /** @var BroadcastsSubscriptions $broadcaster */
        $broadcaster = app(BroadcastsSubscriptions::class);
        /** @var ExceptionHandler $exceptionHandler */
        $exceptionHandler = app(ExceptionHandler::class);

        if (! $registry->has($subscriptionField)) {
            throw new \InvalidArgumentException(
                "No subscription field registered for {$subscriptionField}"
            );
        }

        $shouldQueue = null === $shouldQueue
            ? config('lighthouse.subscriptions.queue_broadcasts', false)
            : $shouldQueue;
        $method = $shouldQueue
            ? BroadcastsSubscriptions::QUEUE_BROADCAST_METHOD_NAME
            : BroadcastsSubscriptions::BROADCAST_METHOD_NAME;

        try {
            call_user_func(
                [$broadcaster, $method],
                $registry->subscription($subscriptionField),
                $subscriptionField,
                $root
            );
        } catch (\Throwable $e) {
            $exceptionHandler->handleBroadcastError($e);
        }
    }
}
