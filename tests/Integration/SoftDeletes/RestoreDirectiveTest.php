<?php

namespace Tests\Integration\SoftDeletes;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\SoftDeletes\RestoreDirective;
use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;
use Tests\Utils\Policies\UserPolicy;

class RestoreDirectiveTest extends DBTestCase
{
    public function testRestoresTaskAndReturnsIt(): void
    {
        $task = factory(Task::class)->create();
        $task->delete();

        $this->assertCount(1, Task::withTrashed()->get());
        $this->assertCount(0, Task::withoutTrashed()->get());

        $this->schema .= /** @lang GraphQL */ '
        type Task {
            id: ID!
        }

        type Mutation {
            restoreTask(id: ID!): Task @restore
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
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

        $this->schema .= /** @lang GraphQL */ '
        type Task {
            id: ID!
            name: String
        }

        type Mutation {
            restoreTasks(id: [ID!]!): [Task!]! @restore
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            restoreTasks(id: [1, 2]) {
                name
            }
        }
        ')->assertJsonCount(2, 'data.restoreTasks');

        $this->assertCount(2, Task::withoutTrashed()->get());
    }

    public function testCanDirectiveExcludesTrashedModelsWhenUsingRestore(): void
    {
        $user = User::create([
            'name' => UserPolicy::ADMIN,
        ]);
        $task = factory(Task::class)->make();
        $user->tasks()->save($task);
        $task->delete();
        $this->be($user);

        $this->assertCount(0, Task::withoutTrashed()->get());

        $this->schema .= /** @lang GraphQL */ '
        type Task {
            id: ID!
            name: String
        }

        type Mutation {
            restoreTasks(id: ID!): Task!
                @can(ability: "delete", find: "id")
                @restore
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            restoreTasks(id: 1) {
                name
            }
        }
        ')->assertJson([
            'data' => [
                'restoreTasks' => [
                    'name' => $task->name,
                ],
            ],
        ]);

        $this->assertCount(1, Task::withoutTrashed()->get());
    }

    public function testRejectsDefinitionWithNullableArgument(): void
    {
        $this->expectException(DefinitionException::class);

        $this->buildSchema(/** @lang GraphQL */ '
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
        $this->expectException(DefinitionException::class);

        $this->buildSchema(/** @lang GraphQL */ '
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
        $this->expectException(DefinitionException::class);

        $this->buildSchema(/** @lang GraphQL */ '
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

        $this->buildSchema(/** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Query {
            restoreUser(id: ID!): User @restore
        }
        ');
    }
}
