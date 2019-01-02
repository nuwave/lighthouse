<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;
use Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions;
use Nuwave\Lighthouse\Subscriptions\SubscriptionRegistry as Registry;
use Nuwave\Lighthouse\Subscriptions\Contracts\AuthorizesSubscriptions as Auth;
use Nuwave\Lighthouse\Support\Contracts\SubscriptionExceptionHandler as ExceptionHandler;

class Authorizer implements Auth
{
    /** @var StoresSubscriptions */
    protected $storage;

    /** @var Registry */
    protected $registry;

    /** @var ExceptionHandler */
    protected $exceptionHandler;

    /**
     * @param StoresSubscriptions $storage
     * @param Registry            $registry
     * @param ExceptionHandler    $exceptionHandler
     */
    public function __construct(
        StoresSubscriptions $storage,
        Registry $registry,
        ExceptionHandler $exceptionHandler
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
    public function authorize(Request $request)
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
                function ($authorized, GraphQLSubscription $subscription) use ($subscriber, $request) {
                    return $authorized === false ? false : $subscription->authorize($subscriber, $request);
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
