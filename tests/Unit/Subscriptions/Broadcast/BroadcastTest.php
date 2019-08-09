<?php

namespace Tests\Unit\Subscriptions\Broadcasts;

use Tests\DBTestCase;
use Mockery\MockInterface;
use Tests\Utils\Models\Task;
use Nuwave\Lighthouse\Execution\Utils\Subscription;
use Nuwave\Lighthouse\Subscriptions\SubscriptionBroadcaster;
use Nuwave\Lighthouse\Subscriptions\SubscriptionServiceProvider;

class BroadcastTest extends DBTestCase
{
    protected $schema = '
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

    protected function setUp(): void
    {
        parent::setUp();

        app()->register(SubscriptionServiceProvider::class);

        factory(Task::class, 1)->create();
    }

    /**
     * @return MockInterface
     */
    public function withMockedBroadcasts(): MockInterface
    {
        $broadcast = \Mockery::mock(SubscriptionBroadcaster::class);
        $this->app->instance(SubscriptionBroadcaster::class, $broadcast);

        return $broadcast;
    }

    /**
     * @test
     */
    public function itBroadcastsFromPhp(): void
    {
        // Assert
        $broadcast = $this->withMockedBroadcasts();
        $broadcast->shouldReceive('broadcast')->once();

        // Subscribe to event
        $this->postGraphQL([
            'query' => '
                subscription UserUpdated {
                    taskUpdated {
                        name
                    }
                }
            ',
        ]);

        // Broadcast
        Subscription::broadcast('taskUpdated', []);
    }

    /**
     * @test
     */
    public function itBroadcastsFromSchema(): void
    {
        // Assert
        $broadcast = $this->withMockedBroadcasts();
        $broadcast->shouldReceive('broadcast')->once();

        // Subscribe to event
        $this->postGraphQL([
            'query' => '
                subscription TaskUpdated {
                    taskUpdated {
                        name
                    }
                }
            ',
        ]);

        // Broadcast
        $this->postGraphQL([
            'query' => '
                mutation {
                    updateTask(id: 1, name: "New name") {
                        name
                    }
                }
            ',
        ]);
    }
}
