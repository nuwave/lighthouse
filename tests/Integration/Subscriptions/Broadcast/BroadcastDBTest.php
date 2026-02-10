<?php declare(strict_types=1);

namespace Tests\Integration\Subscriptions\Broadcast;

use Mockery\MockInterface;
use Nuwave\Lighthouse\Execution\Utils\Subscription;
use Nuwave\Lighthouse\Subscriptions\SubscriptionBroadcaster;
use Tests\DBTestCase;
use Tests\EnablesSubscriptionServiceProvider;
use Tests\Utils\Models\Task;

final class BroadcastDBTest extends DBTestCase
{
    use EnablesSubscriptionServiceProvider;

    protected string $schema = /** @lang GraphQL */ <<<'GRAPHQL'
    type Task {
        id: ID!
        name: String!
    }

    type Query {
        task(id: Int @eq): Task @first
    }

    type Mutation {
        updateTask(id: Int! @eq, name: String!): Task @update @broadcast(subscription: "taskUpdated")
    }

    type Subscription {
        taskUpdated: Task
    }
    GRAPHQL;

    protected function setUp(): void
    {
        parent::setUp();

        factory(Task::class, 1)->create();
    }

    public function withMockedBroadcasts(): MockInterface
    {
        $broadcast = \Mockery::mock(SubscriptionBroadcaster::class);
        $this->app->instance(SubscriptionBroadcaster::class, $broadcast);

        return $broadcast;
    }

    public function testBroadcastsFromPhp(): void
    {
        $this->withMockedBroadcasts()
            ->shouldReceive('broadcast')
            ->once();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            subscription UserUpdated {
                taskUpdated {
                    name
                }
            }
        GRAPHQL);

        Subscription::broadcast('taskUpdated', []);
    }

    public function testBroadcastsFromSchema(): void
    {
        $this->withMockedBroadcasts()
            ->shouldReceive('broadcast')
            ->once();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            subscription TaskUpdated {
                taskUpdated {
                    name
                }
            }
        GRAPHQL);

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            mutation {
                updateTask(id: 1, name: "New name") {
                    name
                }
            }
        GRAPHQL);
    }
}
