<?php

namespace Nuwave\Lighthouse\Subscriptions\Contracts;

use Nuwave\Lighthouse\Subscriptions\Subscriber;

interface StoresSubscriptions
{
    /**
     * Find subscriber by channel.
     *
     * @param string $channel
     *
     * @return Subscriber|null
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
     * @param Subscriber $subscriber
     * @param string     $topic
     */
    public function storeSubscriber(Subscriber $subscriber, $topic);

    /**
     * Delete subscriber.
     *
     * @param string $channel
     *
     * @return Subscriber|null
     */
    public function deleteSubscriber($channel);
}
