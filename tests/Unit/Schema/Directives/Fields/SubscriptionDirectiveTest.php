<?php

namespace Tests\Unit\Schema\Directives\Fields;

use Tests\TestCase;
use Illuminate\Http\Request;
use Tests\Utils\Directives\FooSubscription;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;
use Nuwave\Lighthouse\Subscriptions\Storage\MemoryStorage;
use Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions;
use Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions;

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

        $app->singleton(StoresSubscriptions::class, MemoryStorage::class);
        $app->singleton(BroadcastsSubscriptions::class, function () {
            return new class() implements BroadcastsSubscriptions {
                protected $broadcasted = [];

                public function broadcasted($topic = null)
                {
                    if (is_null($topic)) {
                        return $this->broadcasted;
                    }

                    return array_get($this->broadcasted, $topic);
                }

                public function queueBroadcast(GraphQLSubscription $subscription, string $fieldName, $root)
                {
                    $this->broadcast($subscription, $fieldName, $root);
                }

                public function broadcast(GraphQLSubscription $subscription, string $fieldName, $root)
                {
                    $broadcasted = array_get($this->broadcasted, $fieldName, []);

                    $broadcasted[] = $root;

                    $this->broadcasted[$fieldName] = $broadcasted;
                }

                public function authorize($channel, $socketId, Request $request)
                {
                    return true;
                }
            };
        });

        config(['lighthouse.extensions' => [
            \Nuwave\Lighthouse\Schema\Extensions\SubscriptionExtension::class,
        ]]);
    }

    /**
     * @test
     */
    public function itSendsSubscriptionChannelInResponse()
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
        $data = $this->postJson('/graphql', $json)->json();
        $subscriber = app(StoresSubscriptions::class)->subscribersByTopic('ON_POST_CREATED')->first();

        $this->assertInstanceOf(Subscriber::class, $subscriber);
        $this->assertEquals(
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
        $subscribers = app(StoresSubscriptions::class)->subscribersByTopic('ON_POST_CREATED');

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
        $mutation = '
        mutation {
            createPost(post: "Foobar") {
                body
            }
        }';

        $data = $this->execute($this->schema(), $mutation);
        $broadcasted = resolve(BroadcastsSubscriptions::class)->broadcasted();
        $this->assertArrayHasKey('onPostCreated', $broadcasted);
        $this->assertEquals(['body' => 'Foobar'], $broadcasted['onPostCreated'][0]);
    }

    public function resolve($root, array $args)
    {
        return ['body' => $args['post']];
    }

    protected function schema()
    {
        $resolver = addslashes(self::class).'@resolve';
        $subscription = addslashes(FooSubscription::class);

        return "
            type Post {
                body: String
            }
            type Subscription {
                onPostCreated: Post @subscription(class: \"{$subscription}\")
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
