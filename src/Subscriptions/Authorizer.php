<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Exception;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;
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
        } catch (Exception $e) {
            $this->exceptionHandler->handleAuthError($e);

            return false;
        }
    }
}
