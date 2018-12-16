<?php

namespace Nuwave\Lighthouse\Subscriptions\Contracts;

use Illuminate\Support\Collection;
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
    public function subscriberByChannel(string $channel);

    /**
     * Get collection of subscribers by topic.
     *
     * @param string $topic
     *
     * @return Collection
     */
    public function subscribersByTopic(string $topic);

    /**
     * Store subscriber.
     *
     * @param Subscriber $subscriber
     * @param string     $topic
     */
    public function storeSubscriber(Subscriber $subscriber, string $topic);

    /**
     * Delete subscriber.
     *
     * @param string $channel
     *
     * @return Subscriber|null
     */
    public function deleteSubscriber(string $channel);
}
