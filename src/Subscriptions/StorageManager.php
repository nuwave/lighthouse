<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Illuminate\Cache\CacheManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
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
     * The cache to store channels and topics.
     *
     * @var \Illuminate\Contracts\Cache\Repository
     */
    protected $cache;

    /**
     * The time to live for items in the cache.
     *
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

    public function subscriberByRequest(array $input, array $headers): ?Subscriber
    {
        $channel = Arr::get($input, 'channel_name');

        return $channel
            ? $this->subscriberByChannel($channel)
            : null;
    }

    public function subscriberByChannel(string $channel): ?Subscriber
    {
        return $this->cache->get(self::channelKey($channel));
    }

    public function subscribersByTopic(string $topic): Collection
    {
        return $this
            ->retrieveTopic(self::topicKey($topic))
            ->map(function (string $channel): ?Subscriber {
                return $this->subscriberByChannel($channel);
            })
            ->filter();
    }

    public function storeSubscriber(Subscriber $subscriber, string $topic): void
    {
        $subscriber->topic = $topic;
        $this->addSubscriberToTopic($subscriber);

        $channelKey = self::channelKey($subscriber->channel);
        if ($this->ttl === null) {
            $this->cache->forever($channelKey, $subscriber);
        } else {
            $this->cache->put($channelKey, $subscriber, $this->ttl);
        }
    }

    public function deleteSubscriber(string $channel): ?Subscriber
    {
        $subscriber = $this->cache->pull(self::channelKey($channel));

        if ($subscriber !== null) {
            $this->removeSubscriberFromTopic($subscriber);
        }

        return $subscriber;
    }

    /**
     * Store a topic (list of channels) in the cache.
     *
     * @param  \Illuminate\Support\Collection<string>  $topic
     */
    protected function storeTopic(string $key, Collection $topic): void
    {
        if ($this->ttl === null) {
            $this->cache->forever($key, $topic);
        } else {
            $this->cache->put($key, $topic, $this->ttl);
        }
    }

    /**
     * Retrieve a topic (list of channels) from the cache.
     *
     * @return \Illuminate\Support\Collection<string>
     */
    protected function retrieveTopic(string $key): Collection
    {
        return $this->cache->get($key, new Collection());
    }

    /**
     * Add the subscriber to the topic they subscribe to.
     *
     * @param  \Nuwave\Lighthouse\Subscriptions\Subscriber  $subscriber
     */
    protected function addSubscriberToTopic(Subscriber $subscriber): void
    {
        $topicKey = self::topicKey($subscriber->topic);

        $topic = $this->retrieveTopic($topicKey);
        $topic->push($subscriber->channel);
        $this->storeTopic($topicKey, $topic);
    }

    /**
     * Remove the subscriber from the topic they are subscribed to.
     *
     * @param  Subscriber  $subscriber
     */
    protected function removeSubscriberFromTopic(Subscriber $subscriber)
    {
        $topicKey = self::topicKey($subscriber->topic);
        $channelKey = self::channelKey($subscriber->channel);

        $topicWithoutSubscriber = $this
            ->retrieveTopic($topicKey)
            ->reject(function (string $key) use ($channelKey): bool {
                return self::channelKey($key) === $channelKey;
            });

        if ($topicWithoutSubscriber->isEmpty()) {
            $this->cache->forget($topicKey);

            return;
        }

        $this->storeTopic($topicKey, $topicWithoutSubscriber);
    }

    protected static function channelKey(string $channel): string
    {
        return self::SUBSCRIBER_KEY.".{$channel}";
    }

    protected static function topicKey(string $topic): string
    {
        return self::TOPIC_KEY.".{$topic}";
    }
}
