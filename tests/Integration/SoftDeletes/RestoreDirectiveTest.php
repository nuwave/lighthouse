<?php

namespace Tests\Integration\SoftDeletes;

use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Nuwave\Lighthouse\SoftDeletes\RestoreDirective;
use Nuwave\Lighthouse\Exceptions\DirectiveException;

class RestoreDirectiveTest extends DBTestCase
{
    public function testRestoresTaskAndReturnsIt(): void
    {
        $task = factory(Task::class)->create();
        $task->delete();

        $this->assertCount(1, Task::withTrashed()->get());
        $this->assertCount(0, Task::withoutTrashed()->get());

        $this->schema = '
        type Task {
            id: ID!
        }
        
        type Mutation {
            restoreTask(id: ID!): Task @restore
        }
        '.$this->placeholderQuery();

        $this->graphQL('
        mutation {
            restoreTask(id: 1) {
                id
            }
        }
        ')->assertJson([
            'data' => [
                'restoreTask' => [
                    'id' => 1,
                ],
            ],
        ]);

        $this->assertCount(1, Task::withoutTrashed()->get());
    }

    public function testRestoresMultipleTasksAndReturnsThem(): void
    {
        $tasks = factory(Task::class, 2)->create();
        foreach ($tasks as $task) {
            $task->delete();
        }

        $this->assertCount(2, Task::withTrashed()->get());
        $this->assertCount(0, Task::withoutTrashed()->get());

        $this->schema = '
        type Task {
            id: ID!
            name: String
        }
        
        type Mutation {
            restoreTasks(id: [ID!]!): [Task!]! @restore
        }
        '.$this->placeholderQuery();

        $this->graphQL('
        mutation {
            restoreTasks(id: [1, 2]) {
                name
            }
        }
        ')->assertJsonCount(2, 'data.restoreTasks');

        $this->assertCount(2, Task::withoutTrashed()->get());
    }

    public function testRejectsDefinitionWithNullableArgument(): void
    {
        $this->expectException(DirectiveException::class);

        $this->buildSchema('
        type Task {
            id: ID!
        }
        
        type Query {
            restoreTask(id: ID): Task @restore
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
            restoreTask: Task @restore
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
            restoreTask(foo: String, bar: Int): Task @restore
        }
        ');
    }

    public function testRejectsUsingDirectiveWithNoSoftDeleteModels(): void
    {
        $this->expectExceptionMessage(RestoreDirective::MODEL_NOT_USING_SOFT_DELETES);

        $this->buildSchema('
        type User {
            id: ID!
        }
        
        type Query {
            restoreUser(id: ID!): User @restore
        }
        ');
    }
}
