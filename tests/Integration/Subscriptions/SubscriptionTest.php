<?php declare(strict_types=1);

namespace Tests\Integration\Subscriptions;

use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;
use Nuwave\Lighthouse\Subscriptions\Broadcasters\LogBroadcaster;
use Nuwave\Lighthouse\Subscriptions\BroadcastManager;
use Nuwave\Lighthouse\Subscriptions\Storage\CacheStorageManager;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Tests\TestCase;
use Tests\TestsSubscriptions;
use Tests\Utils\Models\User;

final class SubscriptionTest extends TestCase
{
    use TestsSubscriptions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockResolverExpects($this->any())
            ->willReturnCallback(static fn (mixed $root, array $args): array => $args);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Post {
            title: String!
            body: String @guard
        }

        type Subscription {
            onPostCreated: Post
        }

        type Mutation {
            createPost(title: String!, body: String): Post
                @mock
                @broadcast(subscription: "onPostCreated")
        }

        type Query {
            foo: String
        }
GRAPHQL;
    }

    public function testSendsSubscriptionChannelInResponse(): void
    {
        $response = $this->subscribe();

        $cache = $this->app->make(CacheStorageManager::class);

        $subscriber = $cache->subscribersByTopic('ON_POST_CREATED')->first();

        $this->assertInstanceOf(Subscriber::class, $subscriber);
        $response->assertExactJson(
            $this->buildResponse('onPostCreated', $subscriber->channel)
        );
    }

    public function testSendsSubscriptionChannelInBatchedResponse(): void
    {
        $response = $this->postGraphQL([
            [
                'query' => /** @lang GraphQL */ '
                    subscription OnPostCreated1 {
                        onPostCreated {
                            title
                        }
                    }
                    ',
            ],
            [
                'query' => /** @lang GraphQL */ '
                    subscription OnPostCreated2 {
                        onPostCreated {
                            title
                        }
                    }
                    ',
            ],
        ]);

        $cache = $this->app->make(CacheStorageManager::class);

        $subscribers = $cache->subscribersByTopic('ON_POST_CREATED');
        $this->assertCount(2, $subscribers);

        $subscriber1 = $subscribers[0];
        assert($subscriber1 instanceof Subscriber);

        $subscriber2 = $subscribers[1];
        assert($subscriber2 instanceof Subscriber);

        $response->assertExactJson([
            $this->buildResponse('onPostCreated', $subscriber1->channel),
            $this->buildResponse('onPostCreated', $subscriber2->channel),
        ]);
    }

    public function testBroadcastSubscriptions(): void
    {
        $this->subscribe();
        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            createPost(title: "Foobar") {
                title
            }
        }
        ');

        $broadcastManager = $this->app->make(BroadcastManager::class);

        $log = $broadcastManager->driver();
        assert($log instanceof LogBroadcaster);

        $broadcasts = $log->broadcasts();

        $this->assertNotNull($broadcasts);
        /** @var array<mixed> $broadcasts */
        $this->assertCount(1, $broadcasts);

        $broadcasted = Arr::get(Arr::first($broadcasts), 'data', []);
        $this->assertArrayHasKey('onPostCreated', $broadcasted);
        $this->assertSame(['title' => 'Foobar'], $broadcasted['onPostCreated']);
    }

    public function testWithFieldAlias(): void
    {
        $response = $this->graphQL(/** @lang GraphQL */ '
        subscription {
            alias: onPostCreated {
                title
            }
        }
        ');

        /** @var \Nuwave\Lighthouse\Subscriptions\Storage\CacheStorageManager $cache */
        $cache = $this->app->make(CacheStorageManager::class);

        $subscriber = $cache
            ->subscribersByTopic('ON_POST_CREATED')
            ->first();
        self::assertNotNull($subscriber);

        $response->assertExactJson([
            'data' => [
                'alias' => null,
            ],
            'extensions' => [
                'lighthouse_subscriptions' => [
                    'version' => 1,
                    'channel' => $subscriber->channel,
                    'channels' => [
                        'onPostCreated' => $subscriber->channel,
                    ],
                ],
            ],
        ]);
    }

    public function testWithoutExcludeEmpty(): void
    {
        $config = $this->app->make(ConfigRepository::class);

        $config->set('lighthouse.subscriptions.exclude_empty', false);
        $config->set('lighthouse.subscriptions.version', 2);

        $this->subscribe();

        $response = $this->graphQL(/** @lang GraphQL */ '
        query foo {
            foo
        }
        ');

        $response->assertExactJson([
            'data' => [
                'foo' => '42',
            ],
            'extensions' => [
                'lighthouse_subscriptions' => [
                    'version' => 2,
                    'channel' => null,
                ],
            ],
        ]);
    }

    public function testWithExcludeEmpty(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.subscriptions.exclude_empty', true);
        $config->set('lighthouse.subscriptions.version', 2);

        $this->subscribe();

        $response = $this->graphQL(/** @lang GraphQL */ '
        query foo {
            foo
        }
        ');

        $response->assertExactJson([
            'data' => [
                'foo' => '42',
            ],
        ]);
    }

    public function testWithGuard(): void
    {
        $this->be(new User());
        $this->graphQL(/** @lang GraphQL */ '
            subscription OnPostCreated {
                onPostCreated {
                    body
                }
            }
        ');

        $authFactory = $this->app->make(AuthFactory::class);
        $sessionGuard = $authFactory->guard();
        assert($sessionGuard instanceof SessionGuard);
        $sessionGuard->logout();

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            createPost(title: "foo", body: "bar") {
                title
            }
        }
        ');

        $broadcastManager = $this->app->make(BroadcastManager::class);
        $log = $broadcastManager->driver();
        assert($log instanceof LogBroadcaster);

        $broadcasts = $log->broadcasts();

        $this->assertIsArray($broadcasts);
        $this->assertCount(1, $broadcasts);

        $this->assertSame(
            [
                'onPostCreated' => [
                    'body' => 'bar',
                ],
            ],
            Arr::first($broadcasts)['data'] ?? null
        );
    }

    protected function subscribe(): TestResponse
    {
        return $this->graphQL(/** @lang GraphQL */ '
            subscription OnPostCreated {
                onPostCreated {
                    title
                }
            }
        ');
    }

    /**
     * Build the expectation for the first subscription response.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function buildResponse(string $channelName, string $channel): array
    {
        return [
            'data' => [
                'onPostCreated' => null,
            ],
            'extensions' => [
                'lighthouse_subscriptions' => [
                    'version' => 1,
                    'channel' => $channel,
                    'channels' => [
                        $channelName => $channel,
                    ],
                ],
            ],
        ];
    }
}
