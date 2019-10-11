<?php

namespace Tests\Integration\Subscriptions\Broadcast;

use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Nuwave\Lighthouse\Execution\Utils\Subscription;
use Nuwave\Lighthouse\Subscriptions\SubscriptionBroadcaster;
use Nuwave\Lighthouse\Subscriptions\SubscriptionServiceProvider;

class BroadcastDBTest extends DBTestCase
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

    protected function getPackageProviders($app)
    {
        return array_merge(
            parent::getPackageProviders($app),
            [SubscriptionServiceProvider::class]
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        factory(Task::class, 1)->create();
    }

    public function testBroadcastsFromPhp(): void
    {
        $this->mock(SubscriptionBroadcaster::class)
            ->shouldReceive('broadcast')
            ->once();

        $this->postGraphQL([
            'query' => '
                subscription UserUpdated {
                    taskUpdated {
                        name
                    }
                }
            ',
        ]);

        Subscription::broadcast('taskUpdated', []);
    }

    public function testBroadcastsFromSchema(): void
    {
        $this->mock(SubscriptionBroadcaster::class)
            ->shouldReceive('broadcast')
            ->once();

        $this->postGraphQL([
            'query' => '
                subscription TaskUpdated {
                    taskUpdated {
                        name
                    }
                }
            ',
        ]);

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
