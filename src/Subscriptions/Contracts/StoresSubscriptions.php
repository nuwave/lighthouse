<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Subscriptions\Contracts;

use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Subscriptions\Subscriber;

interface StoresSubscriptions
{
    /** Find a subscriber by its channel key. */
    public function subscriberByChannel(string $channel): ?Subscriber;

    /**
     * Get all subscribers for a topic.
     *
     * @return Collection<int, \Nuwave\Lighthouse\Subscriptions\Subscriber>
     */
    public function subscribersByTopic(string $topic): Collection;

    /** Store subscriber for a topic. */
    public function storeSubscriber(Subscriber $subscriber, string $topic): void;

    /** Delete subscriber by its channel key. */
    public function deleteSubscriber(string $channel): ?Subscriber;
}
