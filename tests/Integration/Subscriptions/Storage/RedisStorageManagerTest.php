<?php

namespace Tests\Integration\Subscriptions\Storage;

use Illuminate\Support\Facades\Redis;
use Nuwave\Lighthouse\Subscriptions\Storage\RedisStorageManager;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Tests\TestCase;
use Tests\TestsRedis;
use Tests\TestsSubscriptions;

class RedisStorageManagerTest extends TestCase
{
    use TestsRedis;
    use TestsSubscriptions;

    protected $schema = /** @lang GraphQL */'
    type Task {
        id: ID!
        name: String!
    }

    type Subscription {
        taskUpdated(id: ID!): Task
        taskCreated: Task
    }
    '.self::PLACEHOLDER_QUERY;

    public function testSubscriptionStoredWithPrefix(): void
    {
        $response = $this->querySubscription();

        // externally, we do not see a redis prefix
        $channel = $response->json('extensions.lighthouse_subscriptions.channels.taskUpdated');
        $this->assertStringStartsWith('private-lighthouse-', $channel);

        // internally when using the redis driver to access the keys there seems to be no prefix
        $this->assertRedisHas('graphql.subscriber.'.$channel);
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
        $channel = $response->json('extensions.lighthouse_subscriptions.channels.taskUpdated');

        // when it's the only subscriber to a topic, the topic gets deleted with the subscriber
        $this->assertRedisHas('graphql.subscriber.'.$channel);
        $this->assertRedisHas('graphql.topic.TASK_UPDATED');

        $storage->deleteSubscriber($channel);
        $this->assertRedisMissing('graphql.subscriber.'.$channel);
        $this->assertRedisMissing('graphql.topic.TASK_UPDATED');

        // when there are multiple subscribers, the topic stays as long as there are subscribers
        $firstResponse = $this->querySubscription();
        $firstChannel = $firstResponse->json('extensions.lighthouse_subscriptions.channels.taskUpdated');
        $secondResponse = $this->querySubscription();
        $secondChannel = $secondResponse->json('extensions.lighthouse_subscriptions.channels.taskUpdated');
        $this->assertRedisHas('graphql.topic.TASK_UPDATED');

        $storage->deleteSubscriber($firstChannel);
        $this->assertRedisHas('graphql.topic.TASK_UPDATED');

        $storage->deleteSubscriber($secondChannel);
        $this->assertRedisMissing('graphql.topic.TASK_UPDATED');
    }

    public function testSubscribersByTopic(): void
    {
        /** @var RedisStorageManager $storage */
        $storage = $this->app->make(RedisStorageManager::class);

        $this->querySubscription();
        $this->querySubscription();
        $this->querySubscription();
        $this->querySubscription('taskCreated');

        $updatedSubscribers = $storage->subscribersByTopic('TASK_UPDATED');
        $createdSubscribers = $storage->subscribersByTopic('TASK_CREATED');

        $this->assertCount(3, $updatedSubscribers);
        $this->assertCount(1, $createdSubscribers);

        $this->assertContainsOnlyInstancesOf(Subscriber::class, $updatedSubscribers);
        $this->assertContainsOnlyInstancesOf(Subscriber::class, $createdSubscribers);
    }

    /**
     * @return \Illuminate\Testing\TestResponse
     */
    private function querySubscription(string $topic = /** @lang GraphQL */ 'taskUpdated(id: 123)')
    {
        return $this->graphQL(/** @lang GraphQL */ "
        subscription {
            {$topic} {
                id
                name
            }
        }
        ");
    }
}
