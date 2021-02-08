<?php

namespace Tests\Unit\Subscriptions;

use Illuminate\Http\Request;
use InvalidArgumentException;
use Nuwave\Lighthouse\Execution\Utils\Subscription;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;
use Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Subscriptions\SubscriptionBroadcaster;
use Nuwave\Lighthouse\Subscriptions\SubscriptionRegistry;
use Nuwave\Lighthouse\Subscriptions\SubscriptionServiceProvider;
use Tests\TestCase;
use Tests\TestsSubscriptions;

class SubscriptionTest extends TestCase
{
    use TestsSubscriptions;

    /**
     * @var string
     */
    public const SUBSCRIPTION_FIELD = 'postCreated';

    /**
     * @var \Nuwave\Lighthouse\Subscriptions\SubscriptionRegistry
     */
    protected $subscriptionRegistry;

    /**
     * @var \Nuwave\Lighthouse\Subscriptions\SubscriptionBroadcaster&\PHPUnit\Framework\MockObject\MockObject
     */
    protected $broadcaster;

    public function setUp(): void
    {
        parent::setUp();

        $this->mockResolverExpects($this->any())
            ->willReturn(self::SUBSCRIPTION_FIELD);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            subscription: String @mock
        }
        ';

        $this->subscriptionRegistry = app(SubscriptionRegistry::class);
        $this->subscriptionRegistry->register($this->subscription(), self::SUBSCRIPTION_FIELD);

        $this->broadcaster = $this->createMock(SubscriptionBroadcaster::class);
        $this->app->instance(BroadcastsSubscriptions::class, $this->broadcaster);
    }

    protected function getPackageProviders($app): array
    {
        return array_merge(
            parent::getPackageProviders($app),
            [SubscriptionServiceProvider::class]
        );
    }

    public function testCanSendSubscriptionToBroadcaster(): void
    {
        $root = [
            'post' => [
                'id' => 1,
            ],
        ];

        $this->broadcaster
            ->expects($this->once())
            ->method('broadcast')
            ->with(
                $this->isInstanceOf(GraphQLSubscription::class),
                self::SUBSCRIPTION_FIELD,
                $root
            );

        Subscription::broadcast(self::SUBSCRIPTION_FIELD, $root);
    }

    public function testThrowsOnInvalidSubscriptionField(): void
    {
        $this->broadcaster
            ->expects($this->never())
            ->method('broadcast');

        $this->expectException(InvalidArgumentException::class);

        Subscription::broadcast('unknownField', []);
    }

    protected function subscription(): GraphQLSubscription
    {
        return new class extends GraphQLSubscription {
            public function authorize(Subscriber $subscriber, Request $request): bool
            {
                return true;
            }

            public function filter(Subscriber $subscriber, $root): bool
            {
                return true;
            }
        };
    }
}
