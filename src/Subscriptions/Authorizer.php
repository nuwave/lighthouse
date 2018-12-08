<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;
use Nuwave\Lighthouse\Subscriptions\SubscriptionRegistry as Registry;
use Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions as Storage;
use Nuwave\Lighthouse\Subscriptions\Contracts\AuthorizesSubscriptions as Auth;
use Nuwave\Lighthouse\Support\Contracts\SubscriptionExceptionHandler as ExceptionHandler;

class Authorizer implements Auth
{
    /** @var Storage */
    protected $storage;

    /** @var Registry */
    protected $registry;

    /** @var ExceptionHandler */
    protected $exceptionHandler;

    /**
     * @param Storage          $storage
     * @param Registry         $registry
     * @param ExceptionHandler $exceptionHandler
     */
    public function __construct(Storage $storage, Registry $registry, ExceptionHandler $exceptionHandler)
    {
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
            $channel = $this->channel($request);
            $subscriber = $this->storage->subscriberByChannel($channel);
            $subscriptions = $this->registry->subscriptions($subscriber);

            if ($subscriptions->isEmpty()) {
                return false;
            }

            return $subscriptions->reduce(
                function ($authorized, GraphQLSubscription $subscription) use ($subscriber, $request) {
                    return false === $authorized ? false : $subscription->authorize($subscriber, $request);
                }
            );
        } catch (\Exception $e) {
            $this->exceptionHandler->handleAuthError($e);

            return false;
        }
    }

    /**
     * Extract channel name from input.
     *
     * @param Request $request
     *
     * @return string
     */
    protected function channel(Request $request): string
    {
        return $request->input('channel_name');
    }
}
