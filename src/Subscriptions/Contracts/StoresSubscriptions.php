<?php

namespace Nuwave\Lighthouse\Subscriptions\Contracts;

use Nuwave\Lighthouse\Subscriptions\Subscriber;

interface StoresSubscriptions
{
    /**
     * Get subscriber by request.
     *
     * @param  array  $input
     * @param  array  $headers
     * @return \Nuwave\Lighthouse\Subscriptions\Subscriber|null
     */
    public function subscriberByRequest(array $input, array $headers);

    /**
     * Find subscriber by channel.
     *
     * @param  string  $channel
     * @return \Nuwave\Lighthouse\Subscriptions\Subscriber|null
     */
    public function subscriberByChannel(string $channel);

    /**
     * Get collection of subscribers by topic.
     *
     * @param  string  $topic
     * @return \Illuminate\Support\Collection<\Nuwave\Lighthouse\Subscriptions\Subscriber>
     */
    public function subscribersByTopic(string $topic);

    /**
     * Store subscription.
     *
     * @param  \Nuwave\Lighthouse\Subscriptions\Subscriber  $subscriber
     * @param  string  $topic
     * @return void
     */
    public function storeSubscriber(Subscriber $subscriber, string $topic);

    /**
     * Delete subscriber.
     *
     * @param  string  $channel
     * @return \Nuwave\Lighthouse\Subscriptions\Subscriber|null
     */
    public function deleteSubscriber(string $channel);
}
