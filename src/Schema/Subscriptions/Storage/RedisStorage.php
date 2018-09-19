<?php

namespace Nuwave\Lighthouse\Schema\Subscriptions\Storage;

use Illuminate\Support\Facades\Redis;
use Nuwave\Lighthouse\Schema\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Schema\Subscriptions\Contracts\StoresSubscriptions;

class RedisStorage implements StoresSubscriptions
{
    /**
     * Find subscriber by channel.
     *
     * @param string $channel
     */
    public function subscriberByChannel($channel)
    {
        // ...
    }

    /**
     * Get collection of subscribers by channel.
     *
     * @param string $topic
     *
     * @return \Illuminate\Support\Collection
     */
    public function subscribersByTopic($topic)
    {
        // ...
    }

    /**
     * Store subscription.
     *
     * @param Subscriber $subscription
     * @param string     $topic
     */
    public function storeSubscriber(Subscriber $subscription, $topic)
    {
        $channels = $this->channels($key);
        $channels[] = $subscription->channel;

        Redis::set($subscription->key(), json_encode($channels)); // Channel
        Redis::set($subscription->id(), json_encode($subscription->toArray())); // Subscriber
    }

    /**
     * Delete subscriber.
     *
     * @param string $id
     *
     * @return Subscriber|null
     */
    public function deleteSubscriber($id)
    {
        // ...
    }

    /**
     * Get current channels.
     *
     * @param string $key
     *
     * @return array
     */
    protected function channels($key)
    {
        $channels = Redis::get($key);

        return empty($channels) ? [] : json_decode($channels, true);
    }
}
