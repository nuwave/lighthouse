<?php

namespace Tests\Integration\Schema\Fields;

use Tests\TestCase;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Subscriptions\BroadcastManager;
use Nuwave\Lighthouse\Subscriptions\SubscriptionStorage;
use Nuwave\Lighthouse\Subscriptions\Broadcasters\LogBroadcaster;

class SubscriptionFieldTest extends TestCase
{
    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app->make('config')->set(['lighthouse.extensions' => [
            \Nuwave\Lighthouse\Schema\Extensions\SubscriptionExtension::class,
        ]]);
    }

    /**
     * @test
     */
    public function itThrowsIfNoOperationNameIsGiven()
    {
        $subscription = '
        subscription {
            onPostCreated {
                body
            }
        }
        ';

        $this->schema = $this->schema();

        $res = $this->queryViaHttp($subscription);
        $this->assertSame(
            Subscriber::MISSING_OPERATION_NAME,
            Arr::get($res, 'errors.0.message')
        );
    }

    /**
     * @test
     */
    public function itSendsSubscriptionChannelInResponse()
    {
        $data = $this->subscribeOnPostCreated();
        $subscriber = $this->app->make(SubscriptionStorage::class)
            ->subscribersByTopic('ON_POST_CREATED')
            ->first();

        $this->assertInstanceOf(Subscriber::class, $subscriber);
        $this->assertEquals(
            $this->buildResponse('PostOperationName', $subscriber->channel),
            $data
        );
    }

    /**
     * @test
     */
    public function itSendsSubscriptionChannelInBatchedResponse()
    {
        $subscription1 = '
        subscription OnPostCreatedV1 {
            onPostCreated {
                body
            }
        }
        ';

        $subscription2 = '
        subscription OnPostCreatedV2 {
            onPostCreated {
                body
            }
        }
        ';

        $batchedQuery = [
            ['query' => $subscription1],
            ['query' => $subscription2],
        ];

        $this->schema = $this->schema();
        $data = $this->postJson('/graphql', $batchedQuery)->json();
        $subscribers = $this->app->make(SubscriptionStorage::class)->subscribersByTopic('ON_POST_CREATED');

        $this->assertCount(2, $subscribers);

        $expected = [
            $this->buildResponse('OnPostCreatedV1', $subscribers[0]->channel),
            $this->buildResponse('OnPostCreatedV2', $subscribers[1]->channel),
        ];

        $this->assertEquals($expected, $data);
    }

    /**
     * @test
     */
    public function itCanBroadcastSubscriptions()
    {
        $this->subscribeOnPostCreated();

        $mutation = '
        mutation {
            createPost(post: "Foobar") {
                body
            }
        }
        ';
        $this->postJson('/graphql', ['query' => $mutation])->assertJsonFragment([
            'createPost' => [
                'body' => 'Foobar',
            ],
        ]);

        /** @var LogBroadcaster $log */
        $log = app(BroadcastManager::class)->driver();
        $recordedBroadcasts = $log->broadcasts();
        $this->assertCount(1, $recordedBroadcasts);

        $firstBroadcast = $recordedBroadcasts[0]['data'];
        $this->assertArrayHasKey('onPostCreated', $firstBroadcast);
        $this->assertEquals(['body' => 'Foobar'], $firstBroadcast['onPostCreated']);
    }

    public function resolve($root, array $args)
    {
        return ['body' => $args['post']];
    }

    protected function schema(): string
    {
        $resolver = addslashes(self::class).'@resolve';

        return "
        type Post {
            body: String
        }
        
        type Subscription {
            onPostCreated: Post
        }
        
        type Mutation {
            createPost(post: String!): Post
                @field(resolver: \"{$resolver}\")
                @broadcast(subscription: \"onPostCreated\")
        }
        
        type Query {
            foo: String
        }
        ";
    }

    protected function subscribeOnPostCreated(): array
    {
        $subscription = '
        subscription PostOperationName {
            onPostCreated {
                body
            }
        }
        ';

        $this->schema = $this->schema();

        return $this->queryViaHttp($subscription);
    }

    protected function buildResponse(string $channelName, $channel)
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
