<?php

namespace Tests\Integration\Subscriptions;

use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Subscriptions\BroadcastManager;
use Nuwave\Lighthouse\Subscriptions\Storage\CacheStorageManager;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Tests\TestCase;
use Tests\TestsSubscriptions;

class SubscriptionTest extends TestCase
{
    use TestsSubscriptions;

    public function setUp(): void
    {
        parent::setUp();

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        type Post {
            body: String
        }

        type Subscription {
            onPostCreated: Post
        }

        type Mutation {
            createPost(post: String!): Post
                @field(resolver: "{$this->qualifyTestResolver()}")
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
        $subscriber = app(CacheStorageManager::class)->subscribersByTopic('ON_POST_CREATED')->first();

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
                            body
                        }
                    }
                    ',
            ],
            [
                'query' => /** @lang GraphQL */ '
                    subscription OnPostCreated2 {
                        onPostCreated {
                            body
                        }
                    }
                    ',
            ],
        ]);

        $subscribers = app(CacheStorageManager::class)->subscribersByTopic('ON_POST_CREATED');
        $this->assertCount(2, $subscribers);

        $response->assertExactJson([
            $this->buildResponse('onPostCreated', $subscribers[0]->channel),
            $this->buildResponse('onPostCreated', $subscribers[1]->channel),
        ]);
    }

    public function testCanBroadcastSubscriptions(): void
    {
        $this->subscribe();
        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            createPost(post: "Foobar") {
                body
            }
        }
        ');

        /** @var \Nuwave\Lighthouse\Subscriptions\Broadcasters\LogBroadcaster $log */
        $log = app(BroadcastManager::class)->driver();
        $broadcasts = $log->broadcasts();

        $this->assertNotNull($broadcasts);
        /** @var array<mixed> $broadcasts */
        $this->assertCount(1, $broadcasts);

        $broadcasted = Arr::get(Arr::first($broadcasts), 'data', []);
        $this->assertArrayHasKey('onPostCreated', $broadcasted);
        $this->assertSame(['body' => 'Foobar'], $broadcasted['onPostCreated']);
    }

    public function testWithFieldAlias(): void
    {
        $response = $this->graphQL(/** @lang GraphQL */ '
        subscription {
            alias: onPostCreated {
                body
            }
        }
        ');

        /** @var \Nuwave\Lighthouse\Subscriptions\Storage\CacheStorageManager $cache */
        $cache = $this->app->make(CacheStorageManager::class);

        $subscriber = $cache
            ->subscribersByTopic('ON_POST_CREATED')
            ->first();

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

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, string>
     */
    public function resolve($root, array $args): array
    {
        return [
            'body' => $args['post'],
        ];
    }

    /**
     * @return \Illuminate\Testing\TestResponse
     */
    protected function subscribe()
    {
        return $this->graphQL(/** @lang GraphQL */ '
            subscription OnPostCreated {
                onPostCreated {
                    body
                }
            }
        ');
    }

    /**
     * Build the expectation for the first subscription reponse.
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
