<?php

namespace Nuwave\Lighthouse\Subscriptions\Storage;

use Illuminate\Support\Facades\Redis;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Illuminate\Contracts\Cache\Repository as Cache;
use Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions;

class RedisStorage implements StoresSubscriptions
{
    /** @var Cache */
    protected $cache;

    /**
     * @param Cache $cache
     */
    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Find subscriber by channel.
     *
     * @param string $channel
     *
     * @return Subscriber|null
     */
    public function subscriberByChannel($channel)
    {
        $key = "graphql.subscriber.{$channel}";

        return $this->cache->has($key)
            ? Subscriber::unserialize($this->cache->get($key))
            : null;
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
        $key = "graphql.topic.{$topic}";

        if (! $this->cache->has($key)) {
            return collect();
        }

        $channels = json_decode($this->cache->get($key), true);

        return collect($channels)->map(function ($channel) {
            return $this->subscriberByChannel($channel);
        })->filter()->values();
    }

    /**
     * Store subscription.
     *
     * @param Subscriber $subscriber
     * @param string     $topic
     */
    public function storeSubscriber(Subscriber $subscriber, $topic)
    {
        $topicKey = "graphql.topic.{$topic}";
        $subscriberKey = "graphql.subscriber.{$subscriber->channel}";

        $topic = $this->cache->has($topicKey)
            ? json_decode($this->cache->get($topicKey), true)
            : [];

        $topic[] = $subscriber->channel;

        $this->cache->set($topicKey, json_encode($topic));
        $this->cache->set($subscriberKey, json_encode($subscriber->toArray()));
    }

    /**
     * Delete subscriber.
     *
     * @param string $channel
     *
     * @return Subscriber|null
     */
    public function deleteSubscriber($channel)
    {
        $key = "graphql.subscriber.{$channel}";

        return $this->cache->has($key)
            ? Subscriber::unserialize($this->cache->get($key))
            : null;
    }
}
