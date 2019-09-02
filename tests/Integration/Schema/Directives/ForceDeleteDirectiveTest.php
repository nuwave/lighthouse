<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Tests\Utils\Models\User;

class ForceDeleteDirectiveTest extends DBTestCase
{
    /**
     * @test
     */
    public function itForceDeletesTaskAndReturnsIt(): void
    {
        factory(Task::class)->create();

        $this->schema = '
        type Task {
            id: ID!
        }
        
        type Mutation {
            forceDeleteTask(id: ID!): Task @forceDelete
        }
        '.$this->placeholderQuery();

        $this->graphQL('
        mutation {
            forceDeleteTask(id: 1) {
                id
            }
        }
        ')->assertJson([
            'data' => [
                'forceDeleteTask' => [
                    'id' => 1,
                ],
            ],
        ]);

        $this->assertCount(0, Task::withTrashed()->get());
    }

    /**
     * @test
     */
    public function itForceDeletesDeletedTaskAndReturnsIt(): void
    {
        $task = factory(Task::class)->create();
        $task->delete();

        $this->schema = '
        type Task {
            id: ID!
        }
        
        type Mutation {
            forceDeleteTask(id: ID!): Task @forceDelete
        }
        '.$this->placeholderQuery();

        $this->graphQL('
        mutation {
            forceDeleteTask(id: 1) {
                id
            }
        }
        ')->assertJson([
            'data' => [
                'forceDeleteTask' => [
                    'id' => 1,
                ],
            ],
        ]);

        $this->assertCount(0, Task::withTrashed()->get());
    }

    /**
     * @test
     */
    public function itForceDeletesMultipleTasksAndReturnsThem(): void
    {
        factory(Task::class, 2)->create();

        $this->schema = '
        type Task {
            id: ID!
            name: String
        }
        
        type Mutation {
            forceDeleteTasks(id: [ID!]!): [Task!]! @forceDelete
        }
        '.$this->placeholderQuery();

        $this->graphQL('
        mutation {
            forceDeleteTasks(id: [1, 2]) {
                name
            }
        }
        ')->assertJsonCount(2, 'data.forceDeleteTasks');

        $this->assertCount(0, Task::withTrashed()->get());
    }

    /**
     * @test
     */
    public function itRejectsDefinitionWithNullableArgument(): void
    {
        $this->expectException(DirectiveException::class);

        $this->schema = '
        type Task {
            id: ID!
            name: String
        }
        
        type Mutation {
            deleteTask(id: ID): Task @forceDelete
        }
        '.$this->placeholderQuery();

        $this->graphQL('
        mutation {
            deleteTask(id: 1) {
                name
            }
        }
        ');
    }

    /**
     * @test
     */
    public function itRejectsDefinitionWithNoArgument(): void
    {
        $this->expectException(DirectiveException::class);

        $this->schema = '
        type Task {
            id: ID!
            name: String
        }
        
        type Mutation {
            deleteTask: Task @forceDelete
        }
        '.$this->placeholderQuery();

        $this->graphQL('
        mutation {
            deleteTask {
                name
            }
        }
        ');
    }

    /**
     * @test
     */
    public function itRejectsDefinitionWithMultipleArguments(): void
    {
        $this->expectException(DirectiveException::class);

        $this->schema = '
        type Task {
            id: ID!
            name: String
        }
        
        type Mutation {
            deleteTask(foo: String, bar: Int): Task @forceDelete
        }
        '.$this->placeholderQuery();

        $this->graphQL('
        mutation {
            deleteTask {
                name
            }
        }
        ');
    }

    /**
     * @test
     */
    public function itRejectUsingDirectiveWithNoSoftDeleteModels(): void
    {
        factory(User::class)->create();
        $this->expectException(DirectiveException::class);

        $this->schema = '
        type User {
            id: ID!
        }
        
        type Mutation {
            deleteUser(id: ID!): User @forceDelete
        }
        '.$this->placeholderQuery();

        $this->graphQL('
        mutation {
            deleteUser(id: 1) {
                id
            }
        }
        ');
    }
}
