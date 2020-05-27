<?php

namespace Nuwave\Lighthouse\Subscriptions\Contracts;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;

interface BroadcastsSubscriptions
{
    /**
     * Push subscription data to subscribers.
     *
     * @return void
     */
    public function broadcast(GraphQLSubscription $subscription, string $fieldName, $root);

    /**
     * Queue pushing subscription data to subscribers.
     *
     * @return void
     */
    public function queueBroadcast(GraphQLSubscription $subscription, string $fieldName, $root);

    /**
     * Authorize the subscription.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function authorize(Request $request);
}
