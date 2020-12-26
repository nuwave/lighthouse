<?php

namespace Tests\Unit\Subscriptions\Storage;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Redis\Factory;
use Illuminate\Redis\Connections\Connection;
use Nuwave\Lighthouse\Subscriptions\Storage\RedisStorageManager;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Unit\Subscriptions\SubscriptionTestCase;
use Tests\Utils\Subscriptions\DummySubscriber;

class RedisStorageManagerTest extends SubscriptionTestCase
{
    public function testSubscriberByChannel(): void
    {
        /** @var MockObject&Repository $config */
        $config = $this->createMock(Repository::class);
        $redisConnection = $this->createMock(Connection::class);
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

    /**
     * @group foo
     */
    public function testDeleteSubscriber(): void
    {
        $config = $this->createMock(Repository::class);
        $redisConnection = $this->createMock(Connection::class);
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
        $config = $this->createMock(Repository::class);
        $redisConnection = $this->createMock(Connection::class);
        $redisFactory = $this->getRedisFactory($redisConnection);

        $subscriber = new DummySubscriber('private-lighthouse-foo', 'dummy-topic');

        $redisConnection->expects($this->exactly(2))
            ->method('command')
            ->withConsecutive(
                ['sadd', [
                    'graphql.topic.some-topic',
                    'private-lighthouse-foo',
                ]],
                ['set', [
                    'graphql.subscriber.private-lighthouse-foo',
                    'C:41:"Tests\Utils\Subscriptions\DummySubscriber":57:{'.\Safe\json_encode([
                        'channel' => 'private-lighthouse-foo',
                        'topic' => 'some-topic',
                    ]).'}',
                ]]
            );

        $manager = new RedisStorageManager($config, $redisFactory);
        $manager->storeSubscriber($subscriber, 'some-topic');
    }

    public function testSubscribersByTopic(): void
    {
        $config = $this->createMock(Repository::class);
        $redisConnection = $this->createMock(Connection::class);
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
     * @param MockObject&Connection $redisConnection
     * @return MockObject&Factory
     */
    private function getRedisFactory(MockObject $redisConnection): MockObject
    {
        $redisFactory = $this->createMock(Factory::class);
        $redisFactory->expects($this->once())
            ->method('connection')
            ->willReturn($redisConnection);

        return $redisFactory;
    }
}
