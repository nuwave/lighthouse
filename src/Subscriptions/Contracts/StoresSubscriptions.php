<?php

namespace Nuwave\Lighthouse\Subscriptions\Contracts;

use Nuwave\Lighthouse\Subscriptions\Subscriber;

interface StoresSubscriptions
{
    /**
     * Get subscriber by request.
     *
     * @return \Nuwave\Lighthouse\Subscriptions\Subscriber|null
     */
    public function subscriberByRequest(array $input, array $headers);

    /**
     * Find subscriber by channel.
     *
     * @return \Nuwave\Lighthouse\Subscriptions\Subscriber|null
     */
    public function subscriberByChannel(string $channel);

    /**
     * Get collection of subscribers by topic.
     *
     * @return \Illuminate\Support\Collection<\Nuwave\Lighthouse\Subscriptions\Subscriber>
     */
    public function subscribersByTopic(string $topic);

    /**
     * Store subscription.
     *
     * @return void
     */
    public function storeSubscriber(Subscriber $subscriber, string $topic);

    /**
     * Delete subscriber.
     *
     * @return \Nuwave\Lighthouse\Subscriptions\Subscriber|null
     */
    public function deleteSubscriber(string $channel);
}
