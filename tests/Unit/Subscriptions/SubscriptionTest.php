<?php declare(strict_types=1);

namespace Tests\Unit\Subscriptions;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\Utils\Subscription;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;
use Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Subscriptions\SubscriptionBroadcaster;
use Nuwave\Lighthouse\Subscriptions\SubscriptionRegistry;
use Nuwave\Lighthouse\Subscriptions\SubscriptionServiceProvider;
use Tests\EnablesSubscriptionServiceProvider;
use Tests\TestCase;

final class SubscriptionTest extends TestCase
{
    use EnablesSubscriptionServiceProvider;

    protected function getPackageProviders($app): array
    {
        return array_merge(
            parent::getPackageProviders($app),
            [SubscriptionServiceProvider::class],
        );
    }

    public function testBroadcastSubscriptionFromField(): void
    {
        $subscriptionField = 'onPostCreated';
        $this->schema .= /** @lang GraphQL */ <<<GRAPHQL
        type Subscription {
            {$subscriptionField}: ID
        }
        GRAPHQL;

        $broadcaster = $this->createMock(SubscriptionBroadcaster::class);
        $this->app->instance(BroadcastsSubscriptions::class, $broadcaster);

        $root = 1;

        $broadcaster
            ->expects($this->once())
            ->method('broadcast')
            ->with(
                $this->isInstanceOf(GraphQLSubscription::class),
                $subscriptionField,
                $root,
            );

        Subscription::broadcast($subscriptionField, $root);
    }

    public function testBroadcastProgrammaticallyRegisteredSubscription(): void
    {
        $subscriptionRegistry = $this->app->make(SubscriptionRegistry::class);
        $subscription = new class() extends GraphQLSubscription {
            public function authorize(Subscriber $subscriber, Request $request): bool
            {
                return true;
            }

            public function filter(Subscriber $subscriber, mixed $root): bool
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
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Subscription {
            foo: ID @mock
        }
        GRAPHQL;

        $this->expectException(\InvalidArgumentException::class);

        Subscription::broadcast('unknownField', []);
    }

    public function testThrowsOnMissingSubscriptionRootType(): void
    {
        $this->expectException(DefinitionException::class);

        Subscription::broadcast('canNotBeResolvedDueToMissingRootType', []);
    }
}
