<?php declare(strict_types=1);

namespace Tests\Integration\Subscriptions\EchoBroadcaster;

use Illuminate\Testing\TestResponse;
use Nuwave\Lighthouse\Subscriptions\Storage\RedisStorageManager;
use Tests\EnablesSubscriptionServiceProvider;
use Tests\TestCase;
use Tests\TestsRedis;

final class AuthorizeRequestsTest extends TestCase
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
    }
    ' . self::PLACEHOLDER_QUERY;

    public function testEchoClientAuthorizesSuccessfully(): void
    {
        $response = $this->querySubscription();

        $channel = $response->json('extensions.lighthouse_subscriptions.channel');
        $this
            ->postJson('graphql/subscriptions/auth', [
                'channel_name' => $channel,
            ])
            ->assertSuccessful()
            ->assertJsonStructure([
                'channel_data' => [
                    'user_id', 'user_info',
                ],
            ]);
    }

    public function testEchoClientAuthorizesPresenceChannelForBackwardCompatibility(): void
    {
        $response = $this->querySubscription();

        $channel = $response->json('extensions.lighthouse_subscriptions.channel');
        $this
            ->postJson('graphql/subscriptions/auth', [
                'channel_name' => "presence-{$channel}",
            ])
            ->assertSuccessful()
            ->assertJsonStructure([
                'channel_data' => [
                    'user_id', 'user_info',
                ],
            ]);
    }

    public function testEchoClientAuthorizationFailsOtherThanPresenceChannel(): void
    {
        $response = $this->querySubscription();

        $channel = $response->json('extensions.lighthouse_subscriptions.channel');
        $this
            ->postJson('graphql/subscriptions/auth', [
                'channel_name' => "anything-before-{$channel}",
            ])
            ->assertForbidden();
    }

    public function testEchoClientAuthorizeFails(): void
    {
        $response = $this->querySubscription();

        $channel = $response->json('extensions.lighthouse_subscriptions.channel');
        $this
            ->postJson('graphql/subscriptions/auth', [
                'channel_name' => "{$channel}plain-wrong",
            ])
            ->assertForbidden();
    }

    public function testEchoClientAuthorizeFailsAfterDelete(): void
    {
        $response = $this->querySubscription();

        $channel = $response->json('extensions.lighthouse_subscriptions.channel');
        $this
            ->postJson('graphql/subscriptions/auth', [
                'channel_name' => $channel,
            ])
            ->assertSuccessful()
            ->assertJsonStructure([
                'channel_data' => ['user_id', 'user_info'],
            ]);

        $this->app->make(RedisStorageManager::class)
            ->deleteSubscriber($channel);

        $this
            ->postJson('graphql/subscriptions/auth', [
                'channel_name' => $channel,
            ])
            ->assertForbidden();
    }

    protected function querySubscription(): TestResponse
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
