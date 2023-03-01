<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Subscriptions;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Subscriptions\Contracts\AuthorizesSubscriptions;
use Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions;
use Nuwave\Lighthouse\Subscriptions\Contracts\SubscriptionExceptionHandler;

class Authorizer implements AuthorizesSubscriptions
{
    public function __construct(
        protected StoresSubscriptions          $storage,
        protected SubscriptionRegistry         $registry,
        protected SubscriptionExceptionHandler $exceptionHandler)
    {}

    public function authorize(Request $request): bool
    {
        try {
            $channel = $request->input('channel_name');
            if (! is_string($channel)) {
                return false;
            }

            $channel = $this->sanitizeChannelName($channel);

            $subscriber = $this->storage->subscriberByChannel($channel);
            if (null === $subscriber) {
                return false;
            }

            $subscriptions = $this->registry->subscriptions($subscriber);
            if ($subscriptions->isEmpty()) {
                return false;
            }

            foreach ($subscriptions as $subscription) {
                if (! $subscription->authorize($subscriber, $request)) {
                    $this->storage->deleteSubscriber($subscriber->channel);

                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            $this->exceptionHandler->handleAuthError($e);

            return false;
        }
    }

    /**
     * Removes the prefix "presence-" from the channel name.
     *
     * Laravel Echo prefixes channel names with "presence-", but we don't.
     */
    protected function sanitizeChannelName(string $channelName): string
    {
        if (Str::startsWith($channelName, 'presence-')) {
            return Str::substr($channelName, 9);
        }

        return $channelName;
    }
}
