<?php

namespace Nuwave\Lighthouse\Subscriptions\Contracts;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nuwave\Lighthouse\Schema\Fields\SubscriptionField;

interface BroadcastsSubscriptions
{
    const BROADCAST_METHOD_NAME = 'broadcast';
    const QUEUE_BROADCAST_METHOD_NAME = 'broadcast';

    /**
     * Push subscription data to subscribers.
     *
     * @param SubscriptionField $subscription
     * @param string              $fieldName
     * @param mixed               $root
     */
    public function broadcast(SubscriptionField $subscription, string $fieldName, $root);

    /**
     * Queue pushing subscription data to subscribers.
     *
     * @param SubscriptionField $subscription
     * @param string              $fieldName
     * @param mixed               $result
     */
    public function queueBroadcast(SubscriptionField $subscription, string $fieldName, $result);

    /**
     * Authorize the subscription.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function authorize(Request $request);
}
