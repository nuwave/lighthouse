<?php

namespace Nuwave\Lighthouse\Subscriptions\Storage;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions;
use Nuwave\Lighthouse\Subscriptions\Subscriber;

/**
 * Stores subscribers and topics in redis.
 * - Topics are subscriptions like "userCreated" or "userDeleted".
 * - Subscribers are clients that are listening to channels like "private-lighthouse-a7ef3d".
 *
 * This manager stores a SET of subscriber channels and the subscribers itself like this:
 * - graphql.topic.userCreated = [ "presence-lighthouse-1", "presence-lighthouse-2", ... ]
 * - graphql.topic.userDeleted = [ "presence-lighthouse-5", "presence-lighthouse-6", ... ]
 * - graphql.subscriber.presence-lighthouse-1 = { query: "{ id, name }" }
 * - graphql.subscriber.presence-lighthouse-2 = { query: "{ name, created_at }" }
 */
class RedisStorageManager implements StoresSubscriptions
{
    public const TOPIC_KEY = 'graphql.topic';

    public const SUBSCRIBER_KEY = 'graphql.subscriber';

    /**
     * @var \Illuminate\Redis\Connections\Connection
     */
    protected $connection;

    /**
     * The time to live in seconds for items in the cache.
     *
     * @var int|null
     */
    protected $ttl;

    public function __construct(ConfigRepository $config, RedisFactory $redis)
    {
        $this->connection = $redis->connection(
            $config->get('lighthouse.subscriptions.broadcasters.echo.connection') ?? 'default'
        );
        $this->ttl = $config->get('lighthouse.subscriptions.storage_ttl');
    }

    public function subscriberByChannel(string $channel): ?Subscriber
    {
        return $this->getSubscriber(
            $this->channelKey($channel)
        );
    }

    /**
     * @return \Illuminate\Support\Collection<\Nuwave\Lighthouse\Subscriptions\Subscriber>
     */
    public function subscribersByTopic(string $topic): Collection
    {
        // As explained in storeSubscriber, we use redis sets to store the names of subscribers of a topic.
        // We can retrieve all members of a set using the command smembers.
        $subscriberIds = $this->connection->command('smembers', [$this->topicKey($topic)]);
        if (0 === count($subscriberIds)) {
            return new Collection();
        }

        // Since we store the individual subscribers with a prefix,
        // but not in the set, we have to add the prefix here.
        $subscriberIds = array_map([$this, 'channelKey'], $subscriberIds);

        // Using the mget command, we can retrieve multiple values from redis.
        // This is like using multiple get calls (getSubscriber uses the get command).
        $subscribers = $this->connection->command('mget', [$subscriberIds]);

        return (new Collection($subscribers))
            ->filter()
            ->map(function (?string $subscriber): ?Subscriber {
                // Some entries may be expired
                if (null === $subscriber) {
                    return null;
                }

                // Other entries may contain invalid values
                try {
                    return unserialize($subscriber);
                } catch (\ErrorException $e) {
                    return null;
                }
            })
            ->filter();
    }

    public function storeSubscriber(Subscriber $subscriber, string $topic): void
    {
        $subscriber->topic = $topic;

        // In contrast to the CacheStorageManager, we use redis sets.
        // Instead of reading the entire list, adding the subscriber and storing the list;
        // we simply add the name of the subscriber to the set of subscribers of this topic using the sadd command...
        $topicKey = $this->topicKey($topic);
        $this->connection->command('sadd', [
            $topicKey,
            $subscriber->channel,
        ]);
        // ...and refresh the ttl of this set as well.
        if (null !== $this->ttl) {
            $this->connection->command('expire', [$topicKey, $this->ttl]);
        }

        // Lastly, we store the subscriber as a serialized string...
        $setCommand = 'set';
        $setArguments = [
            $this->channelKey($subscriber->channel),
            serialize($subscriber),
        ];
        if (null !== $this->ttl) {
            $setCommand = 'setex';
            array_splice($setArguments, 1, 0, [$this->ttl]);
        }
        $this->connection->command($setCommand, $setArguments);
    }

    public function deleteSubscriber(string $channel): ?Subscriber
    {
        $key = $this->channelKey($channel);
        $subscriber = $this->getSubscriber($key);

        if (null !== $subscriber) {
            // Like in storeSubscriber (but in reverse), we delete the subscriber...
            $this->connection->command('del', [$key]);
            // ...and remove it from the set of subscribers of this topic.
            $this->connection->command('srem', [
                $this->topicKey($subscriber->topic),
                $channel,
            ]);
        }

        return $subscriber;
    }

    protected function getSubscriber(string $channelKey): ?Subscriber
    {
        $subscriber = $this->connection->command('get', [$channelKey]);

        return is_string($subscriber)
            ? unserialize($subscriber)
            : null;
    }

    protected function channelKey(string $channel): string
    {
        return self::SUBSCRIBER_KEY . '.' . $channel;
    }

    protected function topicKey(string $topic): string
    {
        return self::TOPIC_KEY . '.' . $topic;
    }
}
