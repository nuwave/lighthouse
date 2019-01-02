<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;
use Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions;
use Nuwave\Lighthouse\Support\Contracts\SubscriptionExceptionHandler;
use Nuwave\Lighthouse\Subscriptions\Contracts\AuthorizesSubscriptions;

class Authorizer implements AuthorizesSubscriptions
{
    /**
     * @var StoresSubscriptions
     */
    protected $storage;

    /**
     * @var SubscriptionRegistry
     */
    protected $registry;

    /**
     * @var SubscriptionExceptionHandler
     */
    protected $exceptionHandler;

    /**
     * @param StoresSubscriptions $storage
     * @param SubscriptionRegistry $registry
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
            $subscriber = $this->storage->subscriberByRequest(
                $request->input(),
                $request->headers->all()
            );

            if (! $subscriber) {
                return false;
            }

            $subscriptions = $this->registry->subscriptions($subscriber);

            if ($subscriptions->isEmpty()) {
                return false;
            }

            $authorized = $subscriptions->reduce(
                function ($authorized, GraphQLSubscription $subscription) use ($subscriber, $request): bool {
                    return $authorized === false
                        ? false
                        : $subscription->authorize($subscriber, $request);
                }
            );

            if (! $authorized) {
                $this->storage->deleteSubscriber($subscriber->channel);
            }

            return $authorized;
        } catch (\Exception $e) {
            $this->exceptionHandler->handleAuthError($e);

            return false;
        }
    }
}
