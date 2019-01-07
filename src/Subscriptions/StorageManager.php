<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Illuminate\Support\Arr;
use Illuminate\Cache\CacheManager;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Cache\Repository as Cache;
use Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions;

class StorageManager implements StoresSubscriptions
{
    /**
     * The cache key for topics.
     *
     * @var string
     */
    const TOPIC_KEY = 'graphql.topic';

    /**
     * The cache key for subscribers.
     *
     * @var string
     */
    const SUBSCRIBER_KEY = 'graphql.subscriber';

    /**
     * @var \Illuminate\Contracts\Cache\Repository
     */
    protected $cache;

    /**
     * @param  CacheManager  $cache
     */
    public function __construct(CacheManager $cache)
    {
        $store = config('lighthouse.subscriptions.storage', 'redis');

        $this->cache = $cache->store($store);
    }

    /**
     * Get subscriber by request.
     *
     * @param  array  $input
     * @param  array  $headers
     *
     * @return Subscriber|null
     */
    public function subscriberByRequest(array $input, array $headers): ?Subscriber
    {
        $channel = Arr::get($input, 'channel_name');

        return $channel
            ? $this->subscriberByChannel($channel)
            : null;
    }

    /**
     * Find subscriber by channel.
     *
     * @param  string  $channel
     *
     * @return Subscriber|null
     */
    public function subscriberByChannel(string $channel): ?Subscriber
    {
        $key = self::SUBSCRIBER_KEY.".{$channel}";

        return $this->cache->has($key)
            ? Subscriber::unserialize($this->cache->get($key))
            : null;
    }

    /**
     * Get collection of subscribers by channel.
     *
     * @param  string  $topic
     *
     * @return Collection<Subscriber>
     */
    public function subscribersByTopic(string $topic)
    {
        $key = self::TOPIC_KEY.".{$topic}";

        if (! $this->cache->has($key)) {
            return collect();
        }

        $channels = json_decode($this->cache->get($key), true);

        return collect($channels)
            ->map(function (string $channel): ?Subscriber {
                return $this->subscriberByChannel($channel);
            })
            ->filter()
            ->values();
    }

    /**
     * Store subscription.
     *
     * @param  Subscriber  $subscriber
     * @param  string     $topic
     *
     * @return void
     */
    public function storeSubscriber(Subscriber $subscriber, string $topic): void
    {
        $topicKey = self::TOPIC_KEY.".{$topic}";
        $subscriberKey = self::SUBSCRIBER_KEY.".{$subscriber->channel}";

        $topic = $this->cache->has($topicKey)
            ? json_decode($this->cache->get($topicKey), true)
            : [];

        $topic[] = $subscriber->channel;

        $this->cache->forever($topicKey, json_encode($topic));
        $this->cache->forever($subscriberKey, json_encode($subscriber->toArray()));
    }

    /**
     * Delete subscriber.
     *
     * @param  string  $channel
     *
     * @return Subscriber|null
     */
    public function deleteSubscriber(string $channel): ?Subscriber
    {
        $key = self::SUBSCRIBER_KEY.".{$channel}";
        $hasSubscriber = $this->cache->has($key);

        $subscriber = $hasSubscriber
            ? Subscriber::unserialize($this->cache->get($key))
            : null;

        if ($hasSubscriber) {
            $this->cache->forget($key);
        }

        return $subscriber;
    }
}
