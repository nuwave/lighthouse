<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Subscriptions\Contracts\AuthorizesSubscriptions;
use Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions;
use Nuwave\Lighthouse\Subscriptions\Contracts\SubscriptionExceptionHandler;

class Authorizer implements AuthorizesSubscriptions
{
    /**
     * @var \Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions
     */
    protected $storage;

    /**
     * @var \Nuwave\Lighthouse\Subscriptions\SubscriptionRegistry
     */
    protected $registry;

    /**
     * @var \Nuwave\Lighthouse\Subscriptions\Contracts\SubscriptionExceptionHandler
     */
    protected $exceptionHandler;

    public function __construct(
        StoresSubscriptions $storage,
        SubscriptionRegistry $registry,
        SubscriptionExceptionHandler $exceptionHandler
    ) {
        $this->storage = $storage;
        $this->registry = $registry;
        $this->exceptionHandler = $exceptionHandler;
    }

    /**
     * Authorize subscription request.
     */
    public function authorize(Request $request): bool
    {
        try {
            if ($request->missing('channel_name')) {
                return false;
            }
            $channel = $this->sanitizeChannelName(
                $request->input('channel_name')
            );

            $subscriber = $this->storage->subscriberByChannel($channel);
            if ($subscriber === null) {
                return false;
            }

            $subscriptions = $this->registry->subscriptions($subscriber);
            if ($subscriptions->isEmpty()) {
                return false;
            }

            /** @var \Nuwave\Lighthouse\Schema\Types\GraphQLSubscription $subscription */
            foreach ($subscriptions as $subscription) {
                if (! $subscription->authorize($subscriber, $request)) {
                    $this->storage->deleteSubscriber($subscriber->channel);

                    return false;
                }
            }

            return true;
        } catch (Exception $e) {
            $this->exceptionHandler->handleAuthError($e);

            return false;
        }
    }

    /**
     * Removes the prefix "presence-" from the channel name.
     *
     * When connecting to a presence channel named "private-lighthouse-subscription-1234"
     * Laravel Echo prefixes the channel with "presence-", but we store the channel
     * without the "presence-" prefix, which is what we have to remove here.
     */
    private function sanitizeChannelName(string $channelName): string
    {
        if (Str::startsWith($channelName, 'presence-')) {
            return Str::substr($channelName, 9);
        }

        return $channelName;
    }
}
