<?php declare(strict_types=1);

namespace Tests\Integration\Subscriptions\Storage;

use Illuminate\Support\Facades\Redis;
use Illuminate\Testing\TestResponse;
use Nuwave\Lighthouse\Subscriptions\Storage\RedisStorageManager;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Tests\EnablesSubscriptionServiceProvider;
use Tests\TestCase;
use Tests\TestsRedis;

final class RedisStorageManagerTest extends TestCase
{
    use TestsRedis;
    use EnablesSubscriptionServiceProvider;

    protected string $schema = /** @lang GraphQL */ '
    type Task {
        id: ID!
        name: String!
    }

    type Subscription {
        taskUpdated(id: ID!): Task
        taskCreated: Task
    }
    ' . self::PLACEHOLDER_QUERY;

    public function testSubscriptionStoredWithPrefix(): void
    {
        $response = $this->querySubscription();

        // externally, we do not see a redis prefix
        $channel = $response->json('extensions.lighthouse_subscriptions.channel');
        $this->assertStringStartsWith('private-lighthouse-', $channel);

        // internally when using the redis driver to access the keys there seems to be no prefix
        $this->assertRedisHas("graphql.subscriber.{$channel}");
        $this->assertRedisHas('graphql.topic.TASK_UPDATED');

        // but in reality redis stores with a prefix
        $keys = Redis::keys('*');
        $this->assertCount(2, $keys);
        $this->assertStringStartsWith('lighthouse-test-', $keys[0]);
        $this->assertStringStartsWith('lighthouse-test-', $keys[1]);
    }

    public function testDeleteSubscriber(): void
    {
        /** @var \Nuwave\Lighthouse\Subscriptions\Storage\RedisStorageManager $storage */
        $storage = $this->app->make(RedisStorageManager::class);

        $response = $this->querySubscription();
        $channel = $response->json('extensions.lighthouse_subscriptions.channel');

        // when it's the only subscriber to a topic, the topic gets deleted with the subscriber
        $this->assertRedisHas("graphql.subscriber.{$channel}");
        $this->assertRedisHas('graphql.topic.TASK_UPDATED');

        $storage->deleteSubscriber($channel);
        $this->assertRedisMissing("graphql.subscriber.{$channel}");
        $this->assertRedisMissing('graphql.topic.TASK_UPDATED');

        // when there are multiple subscribers, the topic stays as long as there are subscribers
        $firstResponse = $this->querySubscription();
        $firstChannel = $firstResponse->json('extensions.lighthouse_subscriptions.channel');
        $secondResponse = $this->querySubscription();
        $secondChannel = $secondResponse->json('extensions.lighthouse_subscriptions.channel');
        $this->assertRedisHas('graphql.topic.TASK_UPDATED');

        $storage->deleteSubscriber($firstChannel);
        $this->assertRedisHas('graphql.topic.TASK_UPDATED');

        $storage->deleteSubscriber($secondChannel);
        $this->assertRedisMissing('graphql.topic.TASK_UPDATED');
    }

    public function testSubscribersByTopic(): void
    {
        $storage = $this->app->make(RedisStorageManager::class);

        $this->querySubscription();
        $this->querySubscription();
        $this->querySubscription();
        $this->querySubscription('taskCreated');

        $unknownSubscribers = $storage->subscribersByTopic('SOMETHING_UNKNOWN');
        $updatedSubscribers = $storage->subscribersByTopic('TASK_UPDATED');
        $createdSubscribers = $storage->subscribersByTopic('TASK_CREATED');

        $this->assertCount(0, $unknownSubscribers);
        $this->assertCount(3, $updatedSubscribers);
        $this->assertCount(1, $createdSubscribers);

        $this->assertContainsOnlyInstancesOf(Subscriber::class, $updatedSubscribers);
        $this->assertContainsOnlyInstancesOf(Subscriber::class, $createdSubscribers);
    }

    public function testSocketIDStoredOnSubscribe(): void
    {
        $storage = $this->app->make(RedisStorageManager::class);

        $this->querySubscription('taskCreated', [
            'X-Socket-ID' => '1234.1234',
        ]);

        $createdSubscriber = $storage->subscribersByTopic('TASK_CREATED')->first();
        self::assertNotNull($createdSubscriber);
        $this->assertSame('1234.1234', $createdSubscriber->socket_id);
    }

    /** @param  array<string, mixed>  $headers */
    protected function querySubscription(string $topic = /** @lang GraphQL */ 'taskUpdated(id: 123)', array $headers = []): TestResponse
    {
        return $this->graphQL(/** @lang GraphQL */ "
        subscription {
            {$topic} {
                id
                name
            }
        }
        ", [], [], $headers);
    }
}
