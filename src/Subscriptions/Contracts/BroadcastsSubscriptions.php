<?php

namespace Nuwave\Lighthouse\Subscriptions\Contracts;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;

interface BroadcastsSubscriptions
{
    /**
     * Push subscription data to subscribers.
     *
     * @param  GraphQLSubscription  $subscription
     * @param  string  $fieldName
     * @param  mixed  $root
     */
    public function broadcast(GraphQLSubscription $subscription, string $fieldName, $root);

    /**
     * Queue pushing subscription data to subscribers.
     *
     * @param  GraphQLSubscription  $subscription
     * @param  string  $fieldName
     * @param  mixed  $root
     */
    public function queueBroadcast(GraphQLSubscription $subscription, string $fieldName, $root);

    /**
     * Authorize the subscription.
     *
     * @param  Request  $request
     *
     * @return Response
     */
    public function authorize(Request $request);
}
