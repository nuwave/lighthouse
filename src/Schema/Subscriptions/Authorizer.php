<?php

namespace Nuwave\Lighthouse\Schema\Subscriptions;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Schema\Subscriptions\SubscriptionRegistry as Registry;
use Nuwave\Lighthouse\Schema\Subscriptions\Contracts\StoresSubscriptions as Storage;
use Nuwave\Lighthouse\Schema\Subscriptions\Contracts\AuthorizesSubscriptions as Auth;

class Authorizer implements Auth
{
    /**
     * @var Storage
     */
    protected $storage;

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @param Storage  $storage
     * @param Registry $registry
     */
    public function __construct(Storage $storage, Registry $registry)
    {
        $this->storage = $storage;
        $this->registry = $registry;
    }

    /**
     * Authorize subscription request.
     *
     * @param string  $channel
     * @param Request $request
     *
     * @return bool
     */
    public function authorize($channel, Request $request)
    {
        try {
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
            return false;
        }
    }
}
