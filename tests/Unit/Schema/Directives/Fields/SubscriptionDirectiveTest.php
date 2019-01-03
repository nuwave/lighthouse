<?php

namespace Tests\Unit\Schema\Directives\Fields;

use Tests\TestCase;
use Illuminate\Support\Arr;
use Tests\Utils\Directives\FooSubscription;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Subscriptions\StorageManager;
use Nuwave\Lighthouse\Subscriptions\BroadcastManager;
use Nuwave\Lighthouse\Subscriptions\Broadcasters\LogBroadcaster;

class SubscriptionDirectiveTest extends TestCase
{
    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set([
            'lighthouse.namespaces.subscriptions' => 'Tests\\Utils\\Directives',
            'lighthouse.extensions' => [
                \Nuwave\Lighthouse\Schema\Extensions\SubscriptionExtension::class,
            ],
        ]);
    }

    /**
     * @test
     */
    public function itSendsSubscriptionChannelInResponse()
    {
        $data = $this->subscribe();
        $subscriber = app(StorageManager::class)->subscribersByTopic('ON_POST_CREATED')->first();

        $this->assertInstanceOf(Subscriber::class, $subscriber);
        $this->assertSame(
            $this->buildResponse('OnPostCreated', $subscriber->channel),
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
        }';

        $subscription2 = '
        subscription OnPostCreatedV2 {
            onPostCreated {
                body
            }
        }';

        $json = [
            ['query' => $subscription1],
            ['query' => $subscription2],
        ];

        $this->schema = $this->schema();
        $data = $this->postJson('/graphql', $json)->json();
        $subscribers = app(StorageManager::class)->subscribersByTopic('ON_POST_CREATED');

        $this->assertCount(2, $subscribers);

        $expected = [
            $this->buildResponse('OnPostCreatedV1', $subscribers[0]->channel),
            $this->buildResponse('OnPostCreatedV2', $subscribers[1]->channel),
        ];

        $this->assertSame($expected, $data);
    }

    /**
     * @test
     */
    public function itCanBroadcastSubscriptions()
    {
        $mutation = '
        mutation {
            createPost(post: "Foobar") {
                body
            }
        }';

        $this->subscribe();
        $this->postJson('/graphql', ['query' => $mutation])->json();

        /** @var LogBroadcaster $log */
        $log = app(BroadcastManager::class)->driver();
        $this->assertCount(1, $log->broadcasts());

        $broadcasted = Arr::get(Arr::first($log->broadcasts()), 'data', []);
        $this->assertArrayHasKey('onPostCreated', $broadcasted);
        $this->assertSame(['body' => 'Foobar'], $broadcasted['onPostCreated']);
    }

    /**
     * @test
     */
    public function itThrowsWithMissingOperationName()
    {
        $subscription = '
        subscription {
            onPostCreated {
                body
            }
        }
        ';

        $json = ['query' => $subscription];

        $this->schema = $this->schema();
        $data = $this->postJson('/graphql', $json)->json();
        $this->assertArrayHasKey('errors', $data);
        $this->assertTrue(Arr::has($data, 'data.onPostCreated'));
        $this->assertTrue(Arr::has($data, 'extensions.lighthouse_subscriptions.channels'));
        $this->assertNull($data['data']['onPostCreated']);
        $this->assertEmpty($data['extensions']['lighthouse_subscriptions']['channels']);
    }

    public function resolve($root, array $args)
    {
        return ['body' => $args['post']];
    }

    protected function schema()
    {
        $resolver = addslashes(self::class).'@resolve';

        return "
            type Post {
                body: String
            }
            type Subscription {
                onPostCreated: Post @subscription(class: \"FooSubscription\")
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

    protected function subscribe()
    {
        $subscription = '
        subscription OnPostCreated {
            onPostCreated {
                body
            }
        }';

        $json = [
            'query' => $subscription,
            'operationName' => 'OnPostCreated',
        ];

        $this->schema = $this->schema();

        return $this->postJson('/graphql', $json)->json();
    }

    protected function buildResponse($channelName, $channel)
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
