<?php

namespace Tests\Integration\Subscriptions\Storage;

use Tests\TestCase;
use Tests\Utils\Models\User;
use GraphQL\Language\AST\NameNode;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use GraphQL\Language\AST\OperationDefinitionNode;
use Nuwave\Lighthouse\Subscriptions\StorageManager;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Subscriptions\Storage\CacheStorage;

class StorageManagerTest extends TestCase implements GraphQLContext
{
    const TOPIC = 'lighthouse';

    /** @var StorageManager */
    protected $storage;

    /**
     * Set up test environment.
     */
    protected function setUp()
    {
        parent::setUp();

        $this->storage = app(StorageManager::class);
    }

    /**
     * @test
     */
    public function itCanStoreSubscribersInCache()
    {
        $subscriber1 = $this->subscriber('{ me }');
        $subscriber2 = $this->subscriber('{ viewer }');
        $this->storage->storeSubscriber($subscriber1, self::TOPIC);
        $this->storage->storeSubscriber($subscriber2, self::TOPIC);

        $this->assertTrue(
            $subscriber1->queryString === $this->storage->subscriberByChannel($subscriber1->channel)->queryString
        );
        $this->assertTrue(
            $subscriber2->queryString === $this->storage->subscriberByChannel($subscriber2->channel)->queryString
        );

        $topicSubscribers = $this->storage->subscribersByTopic(self::TOPIC);
        $this->assertCount(2, $topicSubscribers);

        $this->storage->deleteSubscriber($subscriber1->channel);
        $this->assertCount(1, $this->storage->subscribersByTopic(self::TOPIC));
    }

    protected function subscriber($queryString = null): Subscriber
    {
        $queryString = $queryString ?: '{ me }';
        $info = new ResolveInfo([
            'operation' => new OperationDefinitionNode([
                'name' => new NameNode([
                    'value' => 'foo',
                ]),
            ]),
        ]);

        return Subscriber::initialize(
            'root',
            ['foo' => 'bar'],
            $this,
            $info,
            $queryString
        );
    }

    public function user(): User
    {
        return new User([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@doe.com',
        ]);
    }

    public function request()
    {
        return null;
    }
}
