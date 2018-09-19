<?php

namespace Nuwave\Lighthouse\Schema\Subscriptions\Contracts;

use Nuwave\Lighthouse\Schema\Subscriptions\Subscriber;

interface StoresSubscriptions
{
    /**
     * Find subscriber by channel.
     *
     * @param string $channel
     */
    public function subscriberByChannel($channel);

    /**
     * Get collection of subscribers by topic.
     *
     * @param string $topic
     *
     * @return \Illuminate\Support\Collection
     */
    public function subscribersByTopic($topic);

    /**
     * Store subscription.
     *
     * @param Subscriber $subscription
     * @param string     $topic
     */
    public function storeSubscriber(Subscriber $subscription, $topic);

    /**
     * Delete subscriber.
     *
     * @param string $channel
     *
     * @return Subscriber|null
     */
    public function deleteSubscriber($channel);
}
