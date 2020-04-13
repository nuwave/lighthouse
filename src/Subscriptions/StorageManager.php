<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Illuminate\Cache\CacheManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions;

class StorageManager implements StoresSubscriptions
{
    /**
     * The cache key for topics.
     *
     * @var string
     */
    public const TOPIC_KEY = 'graphql.topic';

    /**
     * The cache key for subscribers.
     *
     * @var string
     */
    public const SUBSCRIBER_KEY = 'graphql.subscriber';

    /**
     * @var \Illuminate\Contracts\Cache\Repository
     */
    protected $cache;

    /**
     * @var \DateInterval|int|null
     */
    protected $ttl;

    public function __construct(CacheManager $cacheManager)
    {
        $this->cache = $cacheManager->store(
            config('lighthouse.subscriptions.storage', 'redis')
        );
        $this->ttl = config('lighthouse.subscriptions.storage_ttl', null);
    }

    /**
     * Get subscriber by request.
     *
     * @return \Nuwave\Lighthouse\Subscriptions\Subscriber|null
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
     * @return \Nuwave\Lighthouse\Subscriptions\Subscriber|null
     */
    public function subscriberByChannel(string $channel): ?Subscriber
    {
        $key = self::SUBSCRIBER_KEY.".{$channel}";

        return $this->cache->get($key);
    }

    /**
     * Get collection of subscribers by channel.
     *
     * @return \Illuminate\Support\Collection<\Nuwave\Lighthouse\Subscriptions\Subscriber>
     */
    public function subscribersByTopic(string $topic): Collection
    {
        $key = self::TOPIC_KEY.".{$topic}";

        if (! $this->cache->has($key)) {
            return new Collection;
        }

        $channels = json_decode($this->cache->get($key), true);

        return (new Collection($channels))
            ->map(function (string $channel): ?Subscriber {
                return $this->subscriberByChannel($channel);
            })
            ->filter()
            ->values();
    }

    /**
     * Store subscription.
     *
     * @param  \Nuwave\Lighthouse\Subscriptions\Subscriber  $subscriber
     */
    public function storeSubscriber(Subscriber $subscriber, string $topic): void
    {
        $topicKey = self::TOPIC_KEY.".{$topic}";
        $subscriberKey = self::SUBSCRIBER_KEY.".{$subscriber->channel}";

        $topic = $this->cache->has($topicKey)
            ? json_decode($this->cache->get($topicKey), true)
            : [];

        $topic[] = $subscriber->channel;

        if ($this->ttl === null) {
            $this->cache->forever($topicKey, json_encode($topic));
            $this->cache->forever($subscriberKey, $subscriber);
        } else {
            $this->cache->put($topicKey, json_encode($topic), $this->ttl);
            $this->cache->put($subscriberKey, $subscriber, $this->ttl);
        }
    }

    /**
     * Delete subscriber.
     *
     * @param  string  $channel
     * @return \Nuwave\Lighthouse\Subscriptions\Subscriber|null
     */
    public function deleteSubscriber(string $channel): ?Subscriber
    {
        $subscriberKey = self::SUBSCRIBER_KEY.".{$channel}";
        $hasSubscriber = $this->cache->has($subscriberKey);
        $subscriber = $this->cache->get($subscriberKey);

        if ($hasSubscriber) {
            $this->removeSubscriberFromTopic($subscriber, $subscriberKey);
            $this->cache->forget($subscriberKey);
        }

        return $subscriber;
    }

    /**
     * Remove the subscriber from the topic they are subscribed to.
     *
     * @param  Subscriber $subscriber
     * @param  string $subscriberKey
     */
    public function removeSubscriberFromTopic(Subscriber $subscriber, string $subscriberKey)
    {
        $key = strtoupper(Str::snake($subscriber->operationName));
        $topicKey = self::TOPIC_KEY.".{$key}";

        $topic = Collection::make(
            $this->cache->has($topicKey)
                ? json_decode($this->cache->get($topicKey), true)
                : []
        )->filter(function ($key) use ($subscriberKey) {
            return $key === $subscriberKey;
        });

        $this->cache->forever($topicKey, json_encode($topic->all()));
    }
}
