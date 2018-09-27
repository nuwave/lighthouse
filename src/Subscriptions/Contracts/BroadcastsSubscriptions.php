<?php

namespace Nuwave\Lighthouse\Subscriptions\Contracts;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;

interface BroadcastsSubscriptions
{
    /**
     * Push subscription data to subscribers.
     *
     * @param GraphQLSubscription $subscription
     * @param string              $fieldName
     * @param mixed               $root
     */
    public function broadcast(GraphQLSubscription $subscription, string $fieldName, $root);

    /**
     * Authorize the subscription.
     *
     * @param string  $channel
     * @param string  $socketId
     * @param Request $request
     *
     * @return array
     */
    public function authorize($channel, $socketId, Request $request);
}
