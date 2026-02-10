<?php declare(strict_types=1);

namespace Tests\Unit\Subscriptions\Storage;

use GraphQL\Language\Parser;
use GraphQL\Utils\AST;
use Nuwave\Lighthouse\Subscriptions\Storage\CacheStorageManager;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Tests\EnablesSubscriptionServiceProvider;
use Tests\TestCase;

final class CacheStorageManagerTest extends TestCase
{
    use EnablesSubscriptionServiceProvider;

    protected CacheStorageManager $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storage = $this->app->make(CacheStorageManager::class);
    }

    /** Construct a dummy subscriber for testing. */
    private function subscriber(string $queryString): Subscriber
    {
        $subscriber = $this->createMock(Subscriber::class);
        $subscriber->channel = Subscriber::uniqueChannelName();
        $subscriber->query = Parser::parse($queryString, ['noLocation' => true]);

        return $subscriber;
    }

    public function testStoreAndRetrieveByChannel(): void
    {
        $subscriber = $this->subscriber(/** @lang GraphQL */ <<<'GRAPHQL'
        { me }
        GRAPHQL);
        $this->storage->storeSubscriber($subscriber, 'foo');

        $this->assertSubscriberIsSame(
            $subscriber,
            $this->storage->subscriberByChannel($subscriber->channel),
        );
    }

    public function testStoreAndRetrieveByTopics(): void
    {
        $fooTopic = 'foo';
        $fooSubscriber1 = $this->subscriber(/** @lang GraphQL */ <<<'GRAPHQL'
        { me }
        GRAPHQL);
        $fooSubscriber2 = $this->subscriber(/** @lang GraphQL */ <<<'GRAPHQL'
        { viewer }
        GRAPHQL);
        $this->storage->storeSubscriber($fooSubscriber1, $fooTopic);
        $this->storage->storeSubscriber($fooSubscriber2, $fooTopic);

        $barTopic = 'bar';
        $barSubscriber = $this->subscriber(/** @lang GraphQL */ <<<'GRAPHQL'
        { bar }
        GRAPHQL);
        $this->storage->storeSubscriber($barSubscriber, $barTopic);

        $fooSubscribers = $this->storage->subscribersByTopic($fooTopic);
        $this->assertCount(2, $fooSubscribers);

        $barSubscribers = $this->storage->subscribersByTopic($barTopic);
        $this->assertCount(1, $barSubscribers);
    }

    public function testDeleteSubscribersInCache(): void
    {
        $subscriber1 = $this->subscriber(/** @lang GraphQL */ <<<'GRAPHQL'
        { me }
        GRAPHQL);
        $subscriber2 = $this->subscriber(/** @lang GraphQL */ <<<'GRAPHQL'
        { viewer }
        GRAPHQL);

        $topic = 'foo';
        $this->storage->storeSubscriber($subscriber1, $topic);
        $this->assertCount(1, $this->storage->subscribersByTopic($topic));

        $this->storage->storeSubscriber($subscriber2, $topic);
        $this->assertCount(2, $this->storage->subscribersByTopic($topic));

        $this->storage->deleteSubscriber($subscriber1->channel);
        $this->assertNull($this->storage->subscriberByChannel($subscriber1->channel));
        $this->assertCount(1, $this->storage->subscribersByTopic($topic));

        $this->storage->deleteSubscriber($subscriber2->channel);
        $this->assertNull($this->storage->subscriberByChannel($subscriber2->channel));
        $this->assertCount(0, $this->storage->subscribersByTopic($topic));
    }

    private function assertSubscriberIsSame(Subscriber $expected, ?Subscriber $actual): void
    {
        $this->assertNotNull($actual);
        $this->assertSame(
            AST::toArray($expected->query),
            AST::toArray($actual->query),
        );
    }
}
