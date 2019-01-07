<?php

namespace Nuwave\Lighthouse\Subscriptions\Contracts;

use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Subscriptions\Subscriber;

interface StoresSubscriptions
{
    /**
     * Get subscriber by request.
     *
     * @param  array  $input
     * @param  array  $headers
     *
     * @return Subscriber|null
     */
    public function subscriberByRequest(array $input, array $headers);

    /**
     * Find subscriber by channel.
     *
     * @param  string  $channel
     *
     * @return Subscriber|null
     */
    public function subscriberByChannel(string $channel);

    /**
     * Get collection of subscribers by topic.
     *
     * @param  string  $topic
     *
     * @return Collection<Subscriber>
     */
    public function subscribersByTopic(string $topic);

    /**
     * Store subscription.
     *
     * @param  Subscriber  $subscriber
     * @param  string  $topic
     */
    public function storeSubscriber(Subscriber $subscriber, string $topic);

    /**
     * Delete subscriber.
     *
     * @param  string  $channel
     *
     * @return Subscriber|null
     */
    public function deleteSubscriber(string $channel);
}
