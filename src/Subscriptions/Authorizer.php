<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Schema\Fields\SubscriptionField;
use Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions;
use Nuwave\Lighthouse\Support\Contracts\SubscriptionExceptionHandler;
use Nuwave\Lighthouse\Subscriptions\Contracts\AuthorizesSubscriptions;

class Authorizer implements AuthorizesSubscriptions
{
    /** @var StoresSubscriptions */
    protected $storage;

    /** @var SubscriptionRegistry */
    protected $registry;

    /** @var SubscriptionExceptionHandler */
    protected $exceptionHandler;

    /**
     * @param StoresSubscriptions          $storage
     * @param SubscriptionRegistry         $registry
     * @param SubscriptionExceptionHandler $exceptionHandler
     */
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
     *
     * @param Request $request
     *
     * @return bool
     */
    public function authorize(Request $request): bool
    {
        try {
            $subscriber = $this->storage->subscriberByChannel(
                $request->input('channel_name')
            );

            if (! $subscriber) {
                return false;
            }

            $subscriptions = $this->registry->subscriptions($subscriber);

            if ($subscriptions->isEmpty()) {
                return false;
            }

            $authorizedForAnySubscriptions = $subscriptions->contains(
                function (SubscriptionField $subscription) use ($subscriber, $request) {
                    return $subscription->authorize($subscriber, $request);
                }
            );

            if (! $authorizedForAnySubscriptions) {
                $this->storage->deleteSubscriber($subscriber->channel);
            }

            return $authorizedForAnySubscriptions;
        } catch (\Exception $e) {
            $this->exceptionHandler->handleAuthError($e);

            return false;
        }
    }
}
