<?php

namespace Tests\Integration\Subscriptions\Broadcast;

use Mockery\MockInterface;
use Nuwave\Lighthouse\Execution\Utils\Subscription;
use Nuwave\Lighthouse\Subscriptions\SubscriptionBroadcaster;
use Tests\DBTestCase;
use Tests\TestsSubscriptions;
use Tests\Utils\Models\Task;

class BroadcastDBTest extends DBTestCase
{
    use TestsSubscriptions;

    protected $schema = /** @lang GraphQL */ '
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
    ';

    public function setUp(): void
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

        $this->graphQL(/** @lang GraphQL */ '
            subscription UserUpdated {
                taskUpdated {
                    name
                }
            }
        ');

        Subscription::broadcast('taskUpdated', []);
    }

    public function testBroadcastsFromSchema(): void
    {
        $this->withMockedBroadcasts()
            ->shouldReceive('broadcast')
            ->once();

        $this->graphQL(/** @lang GraphQL */ '
            subscription TaskUpdated {
                taskUpdated {
                    name
                }
            }
        ');

        $this->graphQL(/** @lang GraphQL */ '
            mutation {
                updateTask(id: 1, name: "New name") {
                    name
                }
            }
        ');
    }
}
