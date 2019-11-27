<?php

namespace Tests\Unit\Subscriptions;

use GraphQL\Language\Parser;
use GraphQL\Utils\AST;
use Nuwave\Lighthouse\Subscriptions\Contracts\ContextSerializer;
use Nuwave\Lighthouse\Subscriptions\StorageManager;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Subscriptions\SubscriptionServiceProvider;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Tests\TestCase;

class StorageManagerTest extends TestCase
{
    /**
     * @var string
     */
    const TOPIC = 'lighthouse';

    /**
     * @var \Nuwave\Lighthouse\Subscriptions\StorageManager
     */
    protected $storage;

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

        $this->storage = app(StorageManager::class);
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app->bind(ContextSerializer::class, function (): ContextSerializer {
            return new class implements ContextSerializer {
                /**
                 * Serialize the context.
                 *
                 * @param  \Nuwave\Lighthouse\Support\Contracts\GraphQLContext  $context
                 * @return string
                 */
                public function serialize(GraphQLContext $context)
                {
                    return 'foo';
                }

                /**
                 * Unserialize the context.
                 *
                 * @param  string  $context
                 * @return \Nuwave\Lighthouse\Support\Contracts\GraphQLContext
                 */
                public function unserialize(string $context)
                {
                    return new class implements GraphQLContext {
                        /**
                         * Get an instance of the authenticated user.
                         *
                         * @return \Illuminate\Foundation\Auth\User|null
                         */
                        public function user()
                        {
                            //
                        }

                        /**
                         * Get an instance of the current HTTP request.
                         *
                         * @return \Illuminate\Http\Request
                         */
                        public function request()
                        {
                            //
                        }
                    };
                }
            };
        });
    }

    /**
     * Construct a dummy subscriber for testing.
     *
     * @param  string  $queryString
     * @return \Nuwave\Lighthouse\Subscriptions\Subscriber
     */
    protected function subscriber(string $queryString): Subscriber
    {
        /** @var \Nuwave\Lighthouse\Subscriptions\Subscriber $subscriber */
        $subscriber = $this->getMockBuilder(Subscriber::class)
            ->disableOriginalConstructor()
            ->getMock();

        $subscriber->channel = Subscriber::uniqueChannelName();
        $subscriber->query = Parser::parse($queryString);

        return $subscriber;
    }

    public function testCanStoreSubscribersInCache(): void
    {
        $subscriber1 = $this->subscriber('{ me }');
        $subscriber2 = $this->subscriber('{ viewer }');
        $subscriber3 = $this->subscriber('{ foo }');
        $this->storage->storeSubscriber($subscriber1, self::TOPIC);
        $this->storage->storeSubscriber($subscriber2, self::TOPIC);
        $this->storage->storeSubscriber($subscriber3, self::TOPIC.'-foo');

        $this->assertSubscriberIsSame(
            $subscriber1,
            $this->storage->subscriberByChannel($subscriber1->channel)
        );
        $this->assertSubscriberIsSame(
            $subscriber2,
            $this->storage->subscriberByChannel($subscriber2->channel)
        );

        $this->assertSubscriberIsSame(
            $subscriber1,
            $this->storage->subscriberByRequest(['channel_name' => $subscriber1->channel], [])
        );
        $this->assertSubscriberIsSame(
            $subscriber2,
            $this->storage->subscriberByRequest(['channel_name' => $subscriber2->channel], [])
        );

        $topicSubscribers = $this->storage->subscribersByTopic(self::TOPIC);
        $this->assertCount(2, $topicSubscribers);

        $this->storage->deleteSubscriber($subscriber1->channel);
        $this->assertCount(1, $this->storage->subscribersByTopic(self::TOPIC));
    }

    protected function assertSubscriberIsSame(Subscriber $expected, Subscriber $actual): void
    {
        $this->assertSame(
            AST::toArray($expected->query),
            AST::toArray($actual->query)
        );
    }
}
