<?php

namespace Tests\Unit\Subscriptions;

use Illuminate\Http\Request;
use InvalidArgumentException;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
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

    protected function getPackageProviders($app): array
    {
        return array_merge(
            parent::getPackageProviders($app),
            [SubscriptionServiceProvider::class]
        );
    }

    public function testBroadcastSubscriptionFromField(): void
    {
        $subscriptionField = 'onPostCreated';
        $this->schema .= /** @lang GraphQL */ "
        type Subscription {
            ${subscriptionField}: ID
        }
        ";

        $broadcaster = $this->createMock(SubscriptionBroadcaster::class);
        $this->app->instance(BroadcastsSubscriptions::class, $broadcaster);

        $root = 1;

        $broadcaster
            ->expects($this->once())
            ->method('broadcast')
            ->with(
                $this->isInstanceOf(GraphQLSubscription::class),
                $subscriptionField,
                $root
            );

        Subscription::broadcast($subscriptionField, $root);
    }

    public function testBroadcastProgrammaticallyRegisteredSubscription(): void
    {
        $subscriptionRegistry = app(SubscriptionRegistry::class);

        $subscription = new class extends GraphQLSubscription
        {
            public function authorize(Subscriber $subscriber, Request $request): bool
            {
                return true;
            }

            public function filter(Subscriber $subscriber, $root): bool
            {
                return true;
            }
        };
        $subscriptionField = 'onPostCreated';
        $subscriptionRegistry->register($subscription, $subscriptionField);

        $broadcaster = $this->createMock(SubscriptionBroadcaster::class);
        $this->app->instance(BroadcastsSubscriptions::class, $broadcaster);

        $root = 1;

        $broadcaster
            ->expects($this->once())
            ->method('broadcast')
            ->with($subscription, $subscriptionField, $root);

        Subscription::broadcast($subscriptionField, $root);
    }

    public function testThrowsOnInvalidSubscriptionField(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Subscription {
            foo: ID @mock
        }
        ';

        $this->expectException(InvalidArgumentException::class);

        Subscription::broadcast('unknownField', []);
    }

    public function testThrowsOnMissingSubscriptionRootType(): void
    {
        $this->expectException(DefinitionException::class);

        Subscription::broadcast('canNotBeResolvedDueToMissingRootType', []);
    }
}
