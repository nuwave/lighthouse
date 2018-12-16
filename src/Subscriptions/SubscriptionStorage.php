<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Repository as Cache;
use Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions;

class SubscriptionStorage implements StoresSubscriptions
{
    const TOPIC_KEY = 'graphql.topic';
    const SUBSCRIBER_KEY = 'graphql.subscriber';

    /** @var Cache */
    protected $cache;

    /**
     * @param CacheManager $cache
     */
    public function __construct(CacheManager $cache)
    {
        $store = config('lighthouse.subscriptions.storage', 'redis');

        $this->cache = $cache->store($store);
    }

    /**
     * Find subscriber by channel.
     *
     * @param string $channel
     *
     * @return Subscriber|null
     */
    public function subscriberByChannel(string $channel)
    {
        return $this->cache->get(
            $this->subscriberKey($channel)
        );
    }

    /**
     * @param string $channel
     *
     * @return string
     */
    protected function subscriberKey(string $channel): string
    {
        return self::SUBSCRIBER_KEY.".{$channel}";
    }

    /**
     * Get collection of subscribers by topic.
     *
     * @param string $topic
     *
     * @return \Illuminate\Support\Collection
     */
    public function subscribersByTopic(string $topic)
    {
        $topicKey = $this->topicKey($topic);

        if (! $this->cache->has($topicKey)) {
            return collect();
        }

        $channels = $this->cache->get($topicKey);

        return collect($channels)
            ->map(function ($channel) {
                return $this->subscriberByChannel($channel);
            })
            ->filter()
            ->values();
    }

    /**
     * @param string $topic
     *
     * @return string
     */
    protected function topicKey(string $topic): string
    {
        return self::TOPIC_KEY.".{$topic}";
    }

    /**
     * Store subscriber.
     *
     * @param Subscriber $subscriber
     * @param string     $topic
     */
    public function storeSubscriber(Subscriber $subscriber, string $topic)
    {
        // Get either an existing topic or an empty array
        $topicKey = $this->topicKey($topic);
        $topic = $this->cache->get($topicKey, []);

        $channel = $subscriber->channel;

        // Add the given channel to the topic and store it back in the cache
        $topic[] = $channel;
        $this->cache->forever($topicKey, $topic);

        $subscriberKey = $this->subscriberKey($channel);
        $this->cache->forever($subscriberKey, $subscriber);
    }

    /**
     * Delete subscriber and indicate if it actually deleted one.
     *
     * @param string $channel
     *
     * @return bool
     */
    public function deleteSubscriber(string $channel): bool
    {
        return $this->cache->forget(
            $this->subscriberKey($channel)
        );
    }
}
