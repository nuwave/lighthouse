<?php

namespace Tests\Unit\Subscriptions\Storage;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection as RedisConnection;
use Nuwave\Lighthouse\Subscriptions\Storage\RedisStorageManager;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;
use Tests\TestsSubscriptions;
use Tests\Utils\Subscriptions\DummySubscriber;

class RedisStorageManagerTest extends TestCase
{
    use TestsSubscriptions;

    public function testSubscriberByChannel(): void
    {
        /** @var \PHPUnit\Framework\MockObject\MockObject&\Illuminate\Contracts\Config\Repository $config */
        $config = $this->createMock(ConfigRepository::class);
        $redisConnection = $this->createMock(RedisConnection::class);
        $redisFactory = $this->getRedisFactory($redisConnection);

        $channel = 'test-channel';
        $subscriber = new DummySubscriber($channel, 'test-topic');
        $redisConnection->expects($this->once())
            ->method('command')
            ->with('get', ['graphql.subscriber.'.$channel])
            ->willReturn(serialize($subscriber));

        $manager = new RedisStorageManager($config, $redisFactory);

        $retrievedSubscriber = $manager->subscriberByChannel($channel);
        $this->assertEquals($subscriber, $retrievedSubscriber);
    }

    public function testDeleteSubscriber(): void
    {
        $config = $this->createMock(ConfigRepository::class);
        $redisConnection = $this->createMock(RedisConnection::class);
        $redisFactory = $this->getRedisFactory($redisConnection);

        $channel = 'test-channel';
        $prefixedChannel = 'graphql.subscriber.'.$channel;
        $subscriber = new DummySubscriber($channel, 'test-topic');
        $redisConnection->expects($this->exactly(3))
            ->method('command')
            ->withConsecutive(
                ['get', [$prefixedChannel]],
                ['del', [$prefixedChannel]],
                ['srem', ['graphql.topic.'.$subscriber->topic, $channel]]
            )
            ->willReturnOnConsecutiveCalls(
                serialize($subscriber)
            );

        $manager = new RedisStorageManager($config, $redisFactory);
        $retrievedSubscriber = $manager->deleteSubscriber($channel);
        $this->assertEquals($subscriber, $retrievedSubscriber);
    }

    public function testStoreSubscriber(): void
    {
        $config = $this->createMock(ConfigRepository::class);
        $redisConnection = $this->createMock(RedisConnection::class);
        $redisFactory = $this->getRedisFactory($redisConnection);

        $ttl = 1000;
        $config->method('get')->willReturn($ttl);

        $channel = 'private-lighthouse-foo';
        $subscriber = new DummySubscriber($channel, 'dummy-topic');

        $topicKey = 'graphql.topic.some-topic';
        $redisConnection->expects($this->exactly(3))
            ->method('command')
            ->withConsecutive(
                ['sadd', [
                    $topicKey,
                    $channel,
                ]],
                ['expire', [
                    $topicKey,
                    $ttl,
                ]],
                ['set', [
                    'graphql.subscriber.private-lighthouse-foo',
                    'C:41:"Tests\Utils\Subscriptions\DummySubscriber":57:{'.\Safe\json_encode([
                        'channel' => $channel,
                        'topic' => 'some-topic',
                    ]).'}',
                    $ttl,
                ]]
            );

        $manager = new RedisStorageManager($config, $redisFactory);
        $manager->storeSubscriber($subscriber, 'some-topic');
    }

    public function testSubscribersByTopic(): void
    {
        $config = $this->createMock(ConfigRepository::class);
        $redisConnection = $this->createMock(RedisConnection::class);
        $redisFactory = $this->getRedisFactory($redisConnection);

        $topic = 'bar';
        $subscribers = [
            new DummySubscriber('foo1', $topic),
            new DummySubscriber('foo2', $topic),
        ];

        $redisConnection->expects($this->exactly(2))
            ->method('command')
            ->withConsecutive(
                ['smembers', ['graphql.topic.'.$topic]],
                ['mget', [[
                    'graphql.subscriber.foo1',
                    'graphql.subscriber.foo2',
                ]]]
            )
            ->willReturnOnConsecutiveCalls(
                ['foo1', 'foo2'],
                array_map('serialize', $subscribers)
            );

        $manager = new RedisStorageManager($config, $redisFactory);
        $this->assertEquals(
            $subscribers,
            $manager->subscribersByTopic($topic)->all()
        );
    }

    /**
     * @param \PHPUnit\Framework\MockObject\MockObject&\Illuminate\Redis\Connections\Connection $redisConnection
     * @return \PHPUnit\Framework\MockObject\MockObject&\Illuminate\Contracts\Redis\Factory
     */
    private function getRedisFactory(MockObject $redisConnection): MockObject
    {
        $redisFactory = $this->createMock(RedisFactory::class);
        $redisFactory->expects($this->once())
            ->method('connection')
            ->willReturn($redisConnection);

        return $redisFactory;
    }
}
