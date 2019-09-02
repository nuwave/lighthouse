<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Tests\Utils\Models\User;

class RestoreDirectiveTest extends DBTestCase
{
    /**
     * @test
     */
    public function itRestoresTaskAndReturnsIt(): void
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

    /**
     * @test
     */
    public function itRestoresMultipleTasksAndReturnsThem(): void
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
            restoreTask(id: ID): Task @restore
        }
        '.$this->placeholderQuery();

        $this->graphQL('
        mutation {
            restoreTask(id: 1) {
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
            restoreTask: Task @restore
        }
        '.$this->placeholderQuery();

        $this->graphQL('
        mutation {
            restoreTask {
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
            restoreTask(foo: String, bar: Int): Task @restore
        }
        '.$this->placeholderQuery();

        $this->graphQL('
        mutation {
            restoreTask {
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
        $user = factory(User::class)->create();
        $this->expectException(DirectiveException::class);

        $this->schema = '
        type User {
            id: ID!
        }
        
        type Mutation {
            restoreUser(id: ID!): User @restore
        }
        '.$this->placeholderQuery();

        $this->graphQL('
        mutation {
            restoreUser(id: 1) {
                id
            }
        }
        ');
    }
}
