<?php

namespace Tests\Integration\Subscriptions;

use Tests\TestCase;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Subscriptions\SubscriptionStorage;

class StorageManagerTest extends TestCase implements GraphQLContext
{
    const TOPIC = 'lighthouse';

    use HandlesSubscribers;

    /** @var SubscriptionStorage */
    protected $storage;

    /**
     * Set up test environment.
     */
    protected function setUp()
    {
        parent::setUp();

        $this->storage = $this->app->make(SubscriptionStorage::class);
    }

    /**
     * @test
     */
    public function itCanStoreSubscribersInCache()
    {
        $subscriber1 = $this->subscriber('{ me }');
        $subscriber2 = $this->subscriber('{ viewer }');
        $subscriber3 = $this->subscriber('{ foo }');
        $this->storage->storeSubscriber($subscriber1, self::TOPIC);
        $this->storage->storeSubscriber($subscriber2, self::TOPIC);
        $this->storage->storeSubscriber($subscriber3, self::TOPIC.'-foo');

        $this->assertEquals(
            $subscriber1->query,
            $this->storage->subscriberByChannel($subscriber1->channel)->query
        );
        $this->assertEquals(
            $subscriber1->queryString,
            $this->storage->subscriberByRequest(['channel_name' => $subscriber1->channel], [])->queryString
        );
        $this->assertEquals(
            $subscriber2->queryString,
            $this->storage->subscriberByRequest(['channel_name' => $subscriber2->channel], [])->queryString
        );

        $topicSubscribers = $this->storage->subscribersByTopic(self::TOPIC);
        $this->assertCount(2, $topicSubscribers);

        $this->storage->deleteSubscriber($subscriber1->channel);
        $this->assertCount(1, $this->storage->subscribersByTopic(self::TOPIC));
    }
}
