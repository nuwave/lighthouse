<?php

namespace Tests\Unit\Schema\Directives\Fields;

use Tests\TestCase;
use Illuminate\Http\Request;
use Tests\Utils\Directives\FooSubscription;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;
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
    public function itCanBroadcastSubscriptions()
    {
        $resolver = addslashes(self::class).'@resolve';
        $subscription = addslashes(FooSubscription::class);

        $schema = "
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
        }";

        $mutation = '
        mutation {
            createPost(post: "Foobar") {
                body
            }
        }';

        $data = $this->execute($schema, $mutation);
        $broadcasted = resolve(BroadcastsSubscriptions::class)->broadcasted();
        $this->assertArrayHasKey('onPostCreated', $broadcasted);
        $this->assertEquals(['body' => 'Foobar'], $broadcasted['onPostCreated'][0]);
    }

    public function resolve($root, array $args)
    {
        return ['body' => $args['post']];
    }
}
