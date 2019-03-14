<?php

namespace Nuwave\Lighthouse\Execution\Utils;

use Throwable;
use InvalidArgumentException;
use Nuwave\Lighthouse\GraphQL;
use Nuwave\Lighthouse\Subscriptions\SubscriptionRegistry;
use Nuwave\Lighthouse\Support\Contracts\SubscriptionExceptionHandler;
use Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions;

class Subscription
{
    /**
     * Broadcast subscription to client(s).
     *
     * @param  string  $subscriptionField
     * @param  mixed  $root
     * @param  bool|null  $shouldQueue
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    public static function broadcast(string $subscriptionField, $root, ?bool $shouldQueue = null): void
    {
        // Ensure we have a schema and registered subscription fields
        // in the event we are calling this method in code.
        /** @var \Nuwave\Lighthouse\GraphQL $graphQL */
        $graphQL = app(GraphQL::class);
        $graphQL->prepSchema();

        /** @var \Nuwave\Lighthouse\Subscriptions\SubscriptionRegistry $registry */
        $registry = app(SubscriptionRegistry::class);

        if (! $registry->has($subscriptionField)) {
            throw new InvalidArgumentException("No subscription field registered for {$subscriptionField}");
        }

        /** @var \Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions $broadcaster */
        $broadcaster = app(BroadcastsSubscriptions::class);

        $shouldQueue = $shouldQueue === null
            ? config('lighthouse.subscriptions.queue_broadcasts', false)
            : $shouldQueue;

        $method = $shouldQueue
            ? 'queueBroadcast'
            : 'broadcast';

        try {
            call_user_func(
                [$broadcaster, $method],
                $registry->subscription($subscriptionField),
                $subscriptionField,
                $root
            );
        } catch (Throwable $e) {
            /** @var \Nuwave\Lighthouse\Support\Contracts\SubscriptionExceptionHandler $exceptionHandler */
            $exceptionHandler = app(SubscriptionExceptionHandler::class);

            $exceptionHandler->handleBroadcastError($e);
        }
    }
}
