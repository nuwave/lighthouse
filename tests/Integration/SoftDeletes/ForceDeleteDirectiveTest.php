<?php

namespace Tests\Integration\SoftDeletes;

use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\SoftDeletes\ForceDeleteDirective;

class ForceDeleteDirectiveTest extends DBTestCase
{
    public function testForceDeletesTaskAndReturnsIt(): void
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

    public function testForceDeletesDeletedTaskAndReturnsIt(): void
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

    public function testForceDeletesMultipleTasksAndReturnsThem(): void
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

    public function testRejectsDefinitionWithNullableArgument(): void
    {
        $this->expectException(DirectiveException::class);

        $this->buildSchema('
        type Task {
            id: ID!
        }
        
        type Query {
            deleteTask(id: ID): Task @forceDelete
        }
        ');
    }

    public function testRejectsDefinitionWithNoArgument(): void
    {
        $this->expectException(DirectiveException::class);

        $this->buildSchema('
        type Task {
            id: ID!
        }
        
        type Query {
            deleteTask: Task @forceDelete
        }
        ');
    }

    public function testRejectsDefinitionWithMultipleArguments(): void
    {
        $this->expectException(DirectiveException::class);

        $this->buildSchema('
        type Task {
            id: ID!
        }
        
        type Query {
            deleteTask(foo: String, bar: Int): Task @forceDelete
        }
        ');
    }

    public function testRejectsUsingDirectiveWithNoSoftDeleteModels(): void
    {
        $this->expectExceptionMessage(ForceDeleteDirective::MODEL_NOT_USING_SOFT_DELETES);
        $this->buildSchema('
        type User {
            id: ID!
        }
        
        type Query {
            deleteUser(id: ID!): User @forceDelete
        }
        ');
    }
}
