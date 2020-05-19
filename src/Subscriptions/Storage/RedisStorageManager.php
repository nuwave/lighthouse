<?php


namespace Nuwave\Lighthouse\Subscriptions\Storage;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Redis\Factory;
use Illuminate\Support\Arr;
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
    /**
     * @var string
     */
    public const TOPIC_KEY = 'graphql.topic';

    /**
     * @var string
     */
    public const SUBSCRIBER_KEY = 'graphql.subscriber';

    /**
     * @var \Illuminate\Redis\Connections\Connection
     */
    private $connection;

    /**
     * The time to live for items in the cache.
     *
     * @var int|null
     */
    protected $ttl;

    /**
     * @param Repository $config
     * @param Factory $redis
     */
    public function __construct(Repository $config, Factory $redis)
    {
        $this->connection = $redis->connection(
            $config->get('lighthouse.broadcasters.redis.connection', 'default')
        );
        $this->ttl = config('lighthouse.subscriptions.storage_ttl', null);
    }

    /**
     * @param array $input
     * @param array $headers
     * @return Subscriber|null
     * @deprecated will be removed in favor of subscriberByChannel
     */
    public function subscriberByRequest(array $input, array $headers): ?Subscriber
    {
        $channel = Arr::get($input, 'channel_name');

        return $channel
            ? $this->subscriberByChannel($channel)
            : null;
    }

    /**
     * @param string $channel
     * @return Subscriber|null
     */
    public function subscriberByChannel(string $channel): ?Subscriber
    {
        return $this->getSubscriber(
            $this->channelKey($channel)
        );
    }

    /**
     * @param string $topic
     * @return Collection<Subscriber>
     */
    public function subscribersByTopic(string $topic): Collection
    {
        $subscriberIds = $this->connection->command('smembers', [$this->topicKey($topic)]);
        $subscriberIds = array_map([$this, 'channelKey'], $subscriberIds);
        $subscribers = $this->connection->command('mget', [$subscriberIds]);

        return collect(
            array_map([$this, 'unserialize'], $subscribers)
        )->filter();
    }

    /**
     * @param Subscriber $subscriber
     * @param string $topic
     * @return void
     */
    public function storeSubscriber(Subscriber $subscriber, string $topic)
    {
        $subscriber->topic = $topic;

        $topicKey = $this->topicKey($topic);
        $this->connection->command('sadd', [
            $topicKey,
            $subscriber->channel
        ]);
        if (isset($this->ttl)) {
            $this->connection->command('expire', [$topicKey, $this->ttl]);
        }

        $this->connection->command('set', array_merge([
            $this->channelKey($subscriber->channel),
            $this->serialize($subscriber),
        ], $this->ttl ? ['EX', $this->ttl] : []));
    }

    /**
     * @param string $channel
     * @return Subscriber|null
     */
    public function deleteSubscriber(string $channel)
    {
        $key = $this->channelKey($channel);
        $subscriber = $this->getSubscriber($key);

        if ($subscriber) {
            $this->connection->command('del', [$key]);
            $this->connection->command('srem', [
                $this->topicKey($subscriber->topic),
                $channel
            ]);
        }

        return $subscriber;
    }

    /**
     * @param string $channelKey
     * @return Subscriber|null
     */
    protected function getSubscriber($channelKey): ?Subscriber
    {
        return $this->unserialize(
            $this->connection->command('get', [$channelKey])
        );
    }

    /**
     * @param string $channel
     * @return string
     */
    protected function channelKey(string $channel): string
    {
        return self::SUBSCRIBER_KEY . '.' . $channel;
    }

    /**
     * @param string $topic
     * @return string
     */
    protected function topicKey(string $topic): string
    {
        return self::TOPIC_KEY . '.' . $topic;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    protected function serialize($value)
    {
        return is_numeric($value) && !in_array($value, [INF, -INF]) && !is_nan($value) ? $value : serialize($value);
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    protected function unserialize($value)
    {
        if (false === $value) {
            return null;
        }

        return is_numeric($value) ? $value : unserialize($value);
    }
}
