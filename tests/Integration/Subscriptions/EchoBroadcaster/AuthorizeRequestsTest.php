<?php

namespace Tests\Integration\Subscriptions\EchoBroadcaster;

use Nuwave\Lighthouse\Subscriptions\Storage\RedisStorageManager;
use Tests\TestCase;
use Tests\TestsRedis;
use Tests\TestsSubscriptions;

class AuthorizeRequestsTest extends TestCase
{
    use TestsRedis;
    use TestsSubscriptions;

    protected $schema = /** @lang GraphQL */ '
    type Task {
        id: ID!
        name: String!
    }

    type Subscription {
        taskUpdated(id: ID!): Task
    }
    '.self::PLACEHOLDER_QUERY;

    public function testEchoClientAuthorizesSuccessfully(): void
    {
        $response = $this->querySubscription();

        $channel = $response->json('extensions.lighthouse_subscriptions.channels.taskUpdated');
        $this
            ->postJson('graphql/subscriptions/auth', [
                'channel_name' => 'presence-'.$channel,
            ])
            ->assertSuccessful()
            ->assertJsonStructure([
                'channel_data' => [
                    'user_id', 'user_info',
                ],
            ]);
    }

    public function testEchoClientAuthorizeFails(): void
    {
        $response = $this->querySubscription();

        $channel = $response->json('extensions.lighthouse_subscriptions.channels.taskUpdated');
        $this
            ->postJson('graphql/subscriptions/auth', [
                'channel_name' => 'presence-'.$channel.'plain-wrong',
            ])
            ->assertForbidden();
    }

    public function testEchoClientAuthorizeFailsAfterDelete(): void
    {
        $response = $this->querySubscription();

        $channel = $response->json('extensions.lighthouse_subscriptions.channels.taskUpdated');
        $this
            ->postJson('graphql/subscriptions/auth', [
                'channel_name' => 'presence-'.$channel,
            ])
            ->assertSuccessful()
            ->assertJsonStructure([
                'channel_data' => ['user_id', 'user_info'],
            ]);

        $this->app->make(RedisStorageManager::class)
            ->deleteSubscriber($channel);

        $this
            ->postJson('graphql/subscriptions/auth', [
                'channel_name' => 'presence-'.$channel,
            ])
            ->assertForbidden();
    }

    /**
     * @return \Illuminate\Testing\TestResponse
     */
    private function querySubscription()
    {
        return $this->graphQL(/** @lang GraphQL */ '
        subscription {
            taskUpdated(id: 123) {
                id
                name
            }
        }
        ');
    }
}
