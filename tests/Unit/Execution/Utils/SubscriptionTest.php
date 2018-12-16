<?php

namespace Tests\Unit\Execution\Utils;

use Tests\TestCase;
use Prophecy\Argument;
use Illuminate\Http\Request;
use Prophecy\Prophecy\ObjectProphecy;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Execution\Utils\Subscription;
use Nuwave\Lighthouse\Schema\Fields\SubscriptionField;
use Nuwave\Lighthouse\Subscriptions\SubscriptionRegistry;
use Nuwave\Lighthouse\Subscriptions\SubscriptionBroadcaster;
use Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions;

class SubscriptionTest extends TestCase
{
    const SUBSCRIPTION_FIELD = 'postCreated';

    /** @var SubscriptionRegistry */
    protected $registry;

    /** @var ObjectProphecy */
    protected $broadcaster;

    /**
     * Set up test environment.
     */
    protected function setUp()
    {
        parent::setUp();

        $this->registry = app(SubscriptionRegistry::class);
        $this->registry->registerSubscription($this->subscription(), self::SUBSCRIPTION_FIELD);

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
    public function itCanSendSubscriptionToBroadcaster()
    {
        $root = ['post' => ['id' => 1]];

        $this->broadcaster->broadcast(
            Argument::type(SubscriptionField::class),
            self::SUBSCRIPTION_FIELD,
            $root
        )->shouldBeCalled();

        Subscription::broadcast(self::SUBSCRIPTION_FIELD, $root);
    }

    /**
     * @test
     */
    public function itThrowsOnInvalidSubscriptionField()
    {
        $this->broadcaster->broadcast(Argument::any())->shouldNotBeCalled();
        $this->expectException(\InvalidArgumentException::class);

        Subscription::broadcast('unknownField', []);
    }

    public function resolve()
    {
        return self::SUBSCRIPTION_FIELD;
    }

    protected function subscription(): SubscriptionField
    {
        return new class() extends SubscriptionField {
            public function authorize(Subscriber $subscriber, Request $request)
            {
                return true;
            }

            public function filter(Subscriber $subscriber, $root)
            {
                return true;
            }
        };
    }
}
