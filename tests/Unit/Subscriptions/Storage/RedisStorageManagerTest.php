<?php declare(strict_types=1);

namespace Tests\Unit\Subscriptions\Storage;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection as RedisConnection;
use Nuwave\Lighthouse\Subscriptions\Storage\RedisStorageManager;
use PHPUnit\Framework\Constraint\Callback;
use PHPUnit\Framework\Constraint\Constraint;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\EnablesSubscriptionServiceProvider;
use Tests\TestCase;
use Tests\Utils\Subscriptions\DummySubscriber;

final class RedisStorageManagerTest extends TestCase
{
    use EnablesSubscriptionServiceProvider;

    /**
     * TODO remove when an official replacement for withConsecutive is available.
     *
     * @see https://github.com/sebastianbergmann/phpunit/issues/4026#issuecomment-1418205424
     *
     * @param  array<mixed>  $firstCallArguments
     * @param  array<mixed>  ...$consecutiveCallsArguments
     *
     * @return iterable<\PHPUnit\Framework\Constraint\Callback<mixed>>
     */
    private function withConsecutive(array $firstCallArguments, array ...$consecutiveCallsArguments): iterable
    {
        foreach ($consecutiveCallsArguments as $consecutiveCallArguments) {
            $this->assertSameSize($firstCallArguments, $consecutiveCallArguments, 'Each expected arguments list need to have the same size.');
        }

        $allConsecutiveCallsArguments = [$firstCallArguments, ...$consecutiveCallsArguments];

        $numberOfArguments = count($firstCallArguments);
        $argumentList = [];
        for ($argumentPosition = 0; $argumentPosition < $numberOfArguments; ++$argumentPosition) {
            $argumentList[$argumentPosition] = array_column($allConsecutiveCallsArguments, $argumentPosition);
        }

        $mockedMethodCall = 0;
        $callbackCall = 0;
        foreach ($argumentList as $index => $argument) {
            yield new Callback(
                static function (mixed $actualArgument) use ($argumentList, &$mockedMethodCall, &$callbackCall, $index, $numberOfArguments): bool {
                    $expected = $argumentList[$index][$mockedMethodCall] ?? null;

                    ++$callbackCall;
                    $mockedMethodCall = (int) ($callbackCall / $numberOfArguments);

                    if ($expected instanceof Constraint) {
                        self::assertThat($actualArgument, $expected);
                    } else {
                        self::assertEquals($expected, $actualArgument);
                    }

                    return true;
                },
            );
        }
    }

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
            ->with('get', ["graphql.subscriber.{$channel}"])
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
        $prefixedChannel = "graphql.subscriber.{$channel}";
        $subscriber = new DummySubscriber($channel, 'test-topic');
        $redisConnection->expects($this->exactly(3))
            ->method('command')
            ->with(...$this->withConsecutive(
                ['get', [$prefixedChannel]],
                ['del', [$prefixedChannel]],
                ['srem', ["graphql.topic.{$subscriber->topic}", $channel]],
            ))
            ->willReturnOnConsecutiveCalls(
                serialize($subscriber),
                true,
                true,
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

        $ttl = '1000';
        $config->method('get')->willReturn($ttl);

        $channel = 'private-lighthouse-foo';
        $subscriber = new DummySubscriber($channel, 'dummy-topic');

        $storedTopic = 'some-topic';
        $subscriberUnderTopic = new DummySubscriber($channel, $storedTopic);

        $topicKey = 'graphql.topic.some-topic';
        $redisConnection->expects($this->exactly(3))
            ->method('command')
            ->with(...$this->withConsecutive(
                ['sadd', [
                    $topicKey,
                    $channel,
                ]],
                ['expire', [
                    $topicKey,
                    $ttl,
                ]],
                ['setex', [
                    'graphql.subscriber.private-lighthouse-foo',
                    $ttl,
                    serialize($subscriberUnderTopic),
                ]],
            ));

        $manager = new RedisStorageManager($config, $redisFactory);
        $manager->storeSubscriber($subscriber, $storedTopic);
    }

    public function testStoreSubscriberWithoutTtl(): void
    {
        $config = $this->createMock(ConfigRepository::class);
        $redisConnection = $this->createMock(RedisConnection::class);
        $redisFactory = $this->getRedisFactory($redisConnection);

        $ttl = null;
        $config->method('get')->willReturn($ttl);

        $channel = 'private-lighthouse-foo';
        $subscriber = new DummySubscriber($channel, 'dummy-topic');

        $storedTopic = 'some-topic';
        $subscriberUnderTopic = new DummySubscriber($channel, $storedTopic);

        $topicKey = 'graphql.topic.some-topic';
        $redisConnection->expects($this->exactly(2))
            ->method('command')
            ->with(...$this->withConsecutive(
                ['sadd', [
                    $topicKey,
                    $channel,
                ]],
                ['set', [
                    'graphql.subscriber.private-lighthouse-foo',
                    serialize($subscriberUnderTopic),
                ]],
            ));

        $manager = new RedisStorageManager($config, $redisFactory);
        $manager->storeSubscriber($subscriber, $storedTopic);
    }

    public function testSubscribersByTopic(): void
    {
        $config = $this->createMock(ConfigRepository::class);
        $redisConnection = $this->createMock(RedisConnection::class);
        $redisFactory = $this->getRedisFactory($redisConnection);

        $topic = 'bar';

        $subscriber1 = new DummySubscriber('foo1', $topic);
        $subscriber2 = new DummySubscriber('foo2', $topic);

        $subscribers = [
            $subscriber1,
            $subscriber2,
        ];

        $redisConnection->expects($this->exactly(3))
            ->method('command')
            ->with(...$this->withConsecutive(
                ['smembers', ["graphql.topic.{$topic}"]],
                ['mget', [[
                    'graphql.subscriber.foo1',
                    'graphql.subscriber.foo2',
                    'graphql.subscriber.foo3',
                    'graphql.subscriber.foo4',
                ]]],
                ['srem', ["graphql.topic.{$topic}", 'foo3', 'foo4']],
            ))
            ->willReturnOnConsecutiveCalls(
                ['foo1', 'foo2', 'foo3', 'foo4'],
                [
                    serialize($subscriber1),
                    serialize($subscriber2),
                    // Simulate an expired key, see https://github.com/nuwave/lighthouse/issues/2035
                    null,
                    // Depending on the setup, redis can also return this invalid result https://github.com/nuwave/lighthouse/issues/2085
                    '(nil)',
                    // mget non-existing-entry
                    false,
                ],
                null,
            );

        $this->assertEquals(
            $subscribers,
            (new RedisStorageManager($config, $redisFactory))
                ->subscribersByTopic($topic)
                ->values()
                ->all(),
        );
    }

    /**
     * @param  \PHPUnit\Framework\MockObject\MockObject&\Illuminate\Redis\Connections\Connection  $redisConnection
     *
     * @return \PHPUnit\Framework\MockObject\MockObject&\Illuminate\Contracts\Redis\Factory
     */
    protected function getRedisFactory(MockObject $redisConnection): MockObject
    {
        $redisFactory = $this->createMock(RedisFactory::class);
        $redisFactory->expects($this->once())
            ->method('connection')
            ->willReturn($redisConnection);

        return $redisFactory;
    }
}
