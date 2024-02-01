<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Subscriptions\Storage;

use GraphQL\Utils\Utils;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions;
use Nuwave\Lighthouse\Subscriptions\Subscriber;

class CacheStorageManager implements StoresSubscriptions
{
    /** The cache key for topics. */
    public const TOPIC_KEY = 'graphql.topic';

    /** The cache key for subscribers. */
    public const SUBSCRIBER_KEY = 'graphql.subscriber';

    /** The cache to store channels and topics. */
    protected CacheRepository $cache;

    /** The time to live for items in the cache. */
    protected ?int $ttl = null;

    public function __construct(CacheFactory $cacheFactory, ConfigRepository $config)
    {
        $storage = $config->get('lighthouse.subscriptions.storage') ?? 'file';
        if (! is_string($storage)) {
            $notStringOrNull = Utils::printSafe($storage);
            throw new \Exception("Expected config option lighthouse.subscriptions.storage to be a string or null, got: {$notStringOrNull}.");
        }

        $this->cache = $cacheFactory->store($storage);

        $ttl = $config->get('lighthouse.subscriptions.storage_ttl');
        if (is_int($ttl) || is_null($ttl)) {
            $this->ttl = $ttl;
        } elseif (is_string($ttl) && is_numeric($ttl)) {
            $this->ttl = (int) $ttl;
        } else {
            $notIntOrNumericString = Utils::printSafe($ttl);
            throw new \Exception("Expected config option lighthouse.subscriptions.storage_ttl to be an int, null or a numeric string, got: {$notIntOrNumericString}.");
        }
    }

    public function subscriberByChannel(string $channel): ?Subscriber
    {
        return $this->cache->get(self::channelKey($channel));
    }

    public function subscribersByTopic(string $topic): Collection
    {
        return $this
            ->retrieveTopic(self::topicKey($topic))
            ->map(fn (string $channel): ?Subscriber => $this->subscriberByChannel($channel))
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
     * @param  \Illuminate\Support\Collection<int, string>  $topic
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
     * @return \Illuminate\Support\Collection<int, string>
     */
    protected function retrieveTopic(string $key): Collection
    {
        return $this->cache->get($key, new Collection());
    }

    /** Add the subscriber to the topic they subscribe to. */
    protected function addSubscriberToTopic(Subscriber $subscriber): void
    {
        $topicKey = self::topicKey($subscriber->topic);

        $topic = $this->retrieveTopic($topicKey);
        $topic->push($subscriber->channel);
        $this->storeTopic($topicKey, $topic);
    }

    /** Remove the subscriber from the topic they are subscribed to. */
    protected function removeSubscriberFromTopic(Subscriber $subscriber): void
    {
        $topicKey = self::topicKey($subscriber->topic);
        $channelKeyToRemove = self::channelKey($subscriber->channel);

        $topicWithoutSubscriber = $this
            ->retrieveTopic($topicKey)
            ->reject(static fn (string $channel): bool => self::channelKey($channel) === $channelKeyToRemove);

        if ($topicWithoutSubscriber->isEmpty()) {
            $this->cache->forget($topicKey);

            return;
        }

        $this->storeTopic($topicKey, $topicWithoutSubscriber);
    }

    protected static function channelKey(string $channel): string
    {
        return self::SUBSCRIBER_KEY . ".{$channel}";
    }

    protected static function topicKey(string $topic): string
    {
        return self::TOPIC_KEY . ".{$topic}";
    }
}
