<?php declare(strict_types=1);

namespace Tests\Integration\SoftDeletes;

use Nuwave\Lighthouse\SoftDeletes\RestoreDirective;
use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;
use Tests\Utils\Policies\UserPolicy;

final class RestoreDirectiveTest extends DBTestCase
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
            restoreTask(id: ID! @whereKey): Task @restore
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
            restoreTasks(id: [ID!]! @whereKey): [Task!]! @restore
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

    public function testDirectiveExcludesTrashedModelsWhenUsingRestore(): void
    {
        $user = new User();
        $user->name = UserPolicy::ADMIN;
        $user->save();
        $this->be($user);

        $task = factory(Task::class)->make();
        $user->tasks()->save($task);
        $task->delete();

        $this->assertCount(0, Task::withoutTrashed()->get());

        $this->schema .= /** @lang GraphQL */ '
        type Task {
            id: ID!
            name: String
        }

        type Mutation {
            restoreTasks(id: ID! @whereKey): Task!
                @can(ability: "delete", query: true)
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

    public function testRejectsUsingDirectiveWithNonSoftDeleteModels(): void
    {
        $this->expectExceptionMessage(RestoreDirective::MODEL_NOT_USING_SOFT_DELETES);

        $this->buildSchema(/** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Query {
            restoreUser(id: ID! @whereKey): User @restore
        }
        ');
    }
}
