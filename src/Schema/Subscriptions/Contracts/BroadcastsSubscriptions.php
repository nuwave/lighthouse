<?php

namespace Nuwave\Lighthouse\Schema\Subscriptions\Contracts;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;

interface BroadcastsSubscriptions
{
    /**
     * Broadcast subscription data.
     *
     * @param GraphQLSubscription $subscription
     * @param string              $fieldName
     * @param mixed|null          $root
     */
    public function broadcast(GraphQLSubscription $subscription, string $fieldName, $root);

    /**
     * Push subscription data to subscribers.
     *
     * @param string $topic
     * @param mixed  $root
     */
    public function push(string $topic, $root);

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
