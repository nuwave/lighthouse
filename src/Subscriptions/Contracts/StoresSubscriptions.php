<?php

namespace Nuwave\Lighthouse\Subscriptions\Contracts;

use Nuwave\Lighthouse\Subscriptions\Subscriber;

interface StoresSubscriptions
{
    /**
     * Get subscriber by request.
     *
     * @deprecated will be removed in favor of subscriberByChannel
     *
     * @return \Nuwave\Lighthouse\Subscriptions\Subscriber|null
     */
    public function subscriberByRequest(array $input, array $headers); // @phpstan-ignore-line

    /**
     * Find a subscriber by its channel key.
     *
     * @return \Nuwave\Lighthouse\Subscriptions\Subscriber|null
     */
    public function subscriberByChannel(string $channel);

    /**
     * Get all subscribers for a topic.
     *
     * @return \Illuminate\Support\Collection<\Nuwave\Lighthouse\Subscriptions\Subscriber>
     */
    public function subscribersByTopic(string $topic);

    /**
     * Store subscriber for a topic.
     *
     * @return void
     */
    public function storeSubscriber(Subscriber $subscriber, string $topic);

    /**
     * Delete subscriber by its channel key.
     *
     * @return \Nuwave\Lighthouse\Subscriptions\Subscriber|null
     */
    public function deleteSubscriber(string $channel);
}
