<?php

namespace Nuwave\Lighthouse\Execution\Utils;

use Illuminate\Container\Container;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
use Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions;
use Nuwave\Lighthouse\Subscriptions\Contracts\SubscriptionExceptionHandler;
use Nuwave\Lighthouse\Subscriptions\SubscriptionRegistry;

class Subscription
{
    /**
     * Broadcast subscription to client(s).
     *
     * @throws \InvalidArgumentException
     */
    public static function broadcast(string $subscriptionField, $root, ?bool $shouldQueue = null): void
    {
        // Ensure we have a schema and registered subscription fields
        // in the event we are calling this method in code.
        $schemaBuilder = Container::getInstance()->make(SchemaBuilder::class);
        assert($schemaBuilder instanceof SchemaBuilder);

        $schemaBuilder->schema();

        $registry = Container::getInstance()->make(SubscriptionRegistry::class);
        assert($registry instanceof SubscriptionRegistry);

        if (! $registry->has($subscriptionField)) {
            throw new \InvalidArgumentException("No subscription field registered for {$subscriptionField}");
        }

        $broadcaster = Container::getInstance()->make(BroadcastsSubscriptions::class);
        assert($broadcaster instanceof BroadcastsSubscriptions);

        // Default to the configuration setting if not specified
        if (null === $shouldQueue) {
            $shouldQueue = config('lighthouse.subscriptions.queue_broadcasts', false);
        }

        $subscription = $registry->subscription($subscriptionField);

        try {
            if ($shouldQueue) {
                $broadcaster->queueBroadcast($subscription, $subscriptionField, $root);
            } else {
                $broadcaster->broadcast($subscription, $subscriptionField, $root);
            }
        } catch (\Throwable $e) {
            $exceptionHandler = Container::getInstance()->make(SubscriptionExceptionHandler::class);
            assert($exceptionHandler instanceof SubscriptionExceptionHandler);

            $exceptionHandler->handleBroadcastError($e);
        }
    }
}
