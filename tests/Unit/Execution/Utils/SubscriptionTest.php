<?php

namespace Tests\Unit\Execution\Utils;

use Tests\TestCase;
use Prophecy\Argument;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Execution\Utils\Subscription;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;
use Nuwave\Lighthouse\Subscriptions\SubscriptionRegistry;
use Nuwave\Lighthouse\Subscriptions\SubscriptionBroadcaster;
use Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions;

class SubscriptionTest extends TestCase
{
    /**
     * @var string
     */
    const SUBSCRIPTION_FIELD = 'postCreated';

    /**
     * @var \Nuwave\Lighthouse\Subscriptions\SubscriptionRegistry
     */
    protected $registry;

    /**
     * @var \Prophecy\Prophecy\ObjectProphecy
     */
    protected $broadcaster;

    protected function setUp()
    {
        parent::setUp();

        $this->registry = app(SubscriptionRegistry::class);
        $this->registry->register($this->subscription(), self::SUBSCRIPTION_FIELD);

        $this->broadcaster = $this->prophesize(SubscriptionBroadcaster::class);
        $this->app->instance(BroadcastsSubscriptions::class, $this->broadcaster->reveal());

        $resolver = addslashes(self::class).'@resolve';
        $this->schema = "
        type Query {
            subscription: String @field(resolver: \"{$resolver}\")
        }
        ";
    }

    /**
     * @test
     */
    public function itCanSendSubscriptionToBroadcaster(): void
    {
        $root = [
            'post' => [
                'id' => 1
            ]
        ];

        $this->broadcaster->broadcast(
            Argument::type(GraphQLSubscription::class),
            self::SUBSCRIPTION_FIELD,
            $root
        )->shouldBeCalled();

        Subscription::broadcast(self::SUBSCRIPTION_FIELD, $root);
    }

    /**
     * @test
     */
    public function itThrowsOnInvalidSubscriptionField(): void
    {
        $this->broadcaster->broadcast(Argument::any())->shouldNotBeCalled();
        $this->expectException(\InvalidArgumentException::class);

        Subscription::broadcast('unknownField', []);
    }

    public function resolve(): string
    {
        return self::SUBSCRIPTION_FIELD;
    }

    protected function subscription(): GraphQLSubscription
    {
        return new class() extends GraphQLSubscription {
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
