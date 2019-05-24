<?php

namespace Tests\Integration\Subscriptions;

use Tests\TestCase;
use Illuminate\Support\Arr;
use Illuminate\Foundation\Testing\TestResponse;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Subscriptions\StorageManager;
use Nuwave\Lighthouse\Subscriptions\BroadcastManager;
use Nuwave\Lighthouse\Subscriptions\SubscriptionServiceProvider;

class SubscriptionTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return array_merge(
            parent::getPackageProviders($app),
            [SubscriptionServiceProvider::class]
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->schema = "
        type Post {
            body: String
        }
        
        type Subscription {
            onPostCreated: Post
        }
        
        type Mutation {
            createPost(post: String!): Post
                @field(resolver: \"{$this->qualifyTestResolver()}\")
                @broadcast(subscription: \"onPostCreated\")
        }
        
        type Query {
            foo: String
        }
        ";
    }

    /**
     * @test
     */
    public function itSendsSubscriptionChannelInResponse(): void
    {
        $response = $this->subscribe();
        $subscriber = app(StorageManager::class)->subscribersByTopic('ON_POST_CREATED')->first();

        $this->assertInstanceOf(Subscriber::class, $subscriber);
        $this->assertSame(
            $this->buildResponse('OnPostCreated', $subscriber->channel),
            $response->jsonGet()
        );
    }

    /**
     * @test
     */
    public function itSendsSubscriptionChannelInBatchedResponse(): void
    {
        $response = $this->postGraphQL([
            [
                'query' => '
                    subscription OnPostCreatedV1 {
                        onPostCreated {
                            body
                        }
                    }
                    ',
            ],
            [
                'query' => '
                    subscription OnPostCreatedV2 {
                        onPostCreated {
                            body
                        }
                    }
                    ',
            ],
        ]);

        $subscribers = app(StorageManager::class)->subscribersByTopic('ON_POST_CREATED');
        $this->assertCount(2, $subscribers);

        $response->assertExactJson([
            $this->buildResponse('OnPostCreatedV1', $subscribers[0]->channel),
            $this->buildResponse('OnPostCreatedV2', $subscribers[1]->channel),
        ]);
    }

    /**
     * @test
     */
    public function itCanBroadcastSubscriptions(): void
    {
        $this->subscribe();
        $this->graphQL('
        mutation {
            createPost(post: "Foobar") {
                body
            }
        }
        ');

        /** @var \Nuwave\Lighthouse\Subscriptions\Broadcasters\LogBroadcaster $log */
        $log = app(BroadcastManager::class)->driver();
        $this->assertCount(1, $log->broadcasts());

        $broadcasted = Arr::get(Arr::first($log->broadcasts()), 'data', []);
        $this->assertArrayHasKey('onPostCreated', $broadcasted);
        $this->assertSame(['body' => 'Foobar'], $broadcasted['onPostCreated']);
    }

    /**
     * @test
     */
    public function itThrowsWithMissingOperationName(): void
    {
        $this->graphQL('
        subscription {
            onPostCreated {
                body
            }
        }
        ')->assertErrorCategory('subscription')
            ->assertJson([
                'data' => [
                    'onPostCreated' => null,
                ],
                'extensions' => [
                    'lighthouse_subscriptions' => [
                        'channels' => [],
                    ],
                ],
            ]);
    }

    /**
     * @param  mixed  $root
     * @param  mixed[]  $args
     * @return mixed[]
     */
    public function resolve($root, array $args): array
    {
        return ['body' => $args['post']];
    }

    protected function subscribe(): TestResponse
    {
        return $this->postGraphQL([
            'query' => '
                subscription OnPostCreated {
                    onPostCreated {
                        body
                    }
                }
            ',
            'operationName' => 'OnPostCreated',
        ]);
    }

    /**
     * Build the expectation for the first subscription reponse.
     *
     * @param  string  $channelName
     * @param  string  $channel
     * @return mixed[]
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
                    'channels' => [
                        $channelName => $channel,
                    ],
                ],
            ],
        ];
    }
}
