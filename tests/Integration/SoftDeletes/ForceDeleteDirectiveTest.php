<?php declare(strict_types=1);

namespace Tests\Integration\SoftDeletes;

use Nuwave\Lighthouse\SoftDeletes\ForceDeleteDirective;
use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;
use Tests\Utils\Policies\UserPolicy;

final class ForceDeleteDirectiveTest extends DBTestCase
{
    public function testForceDeletesTaskAndReturnsIt(): void
    {
        factory(Task::class)->create();

        $this->schema .= /** @lang GraphQL */ '
        type Task {
            id: ID!
        }

        type Mutation {
            forceDeleteTask(id: ID! @whereKey): Task @forceDelete
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
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

        $this->schema .= /** @lang GraphQL */ '
        type Task {
            id: ID!
        }

        type Mutation {
            forceDeleteTask(id: ID! @whereKey): Task @forceDelete
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
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

        $this->schema .= /** @lang GraphQL */ '
        type Task {
            id: ID!
            name: String
        }

        type Mutation {
            forceDeleteTasks(id: [ID!]! @whereKey): [Task!]! @forceDelete
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            forceDeleteTasks(id: [1, 2]) {
                name
            }
        }
        ')->assertJsonCount(2, 'data.forceDeleteTasks');

        $this->assertCount(0, Task::withTrashed()->get());
    }

    public function testDirectiveIncludesTrashedModelsWhenUsingForceDelete(): void
    {
        $user = new User();
        $user->name = UserPolicy::ADMIN;
        $user->save();
        $this->be($user);

        $task = factory(Task::class)->make();
        $user->tasks()->save($task);
        $task->delete();

        $this->schema .= /** @lang GraphQL */ '
        type Task {
            id: ID!
            name: String
        }

        type Mutation {
            forceDeleteTasks(id: ID! @whereKey): Task!
                @can(ability: "delete", query: true)
                @forceDelete
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            forceDeleteTasks(id: 1) {
                name
            }
        }
        ')->assertJsonCount(1, 'data.forceDeleteTasks');

        $this->assertCount(0, Task::withTrashed()->get());
    }

    public function testDirectiveUsesExplicitTrashedArgument(): void
    {
        $user = new User();
        $user->name = UserPolicy::ADMIN;
        $user->save();
        $this->be($user);

        $task = factory(Task::class)->make();
        $user->tasks()->save($task);
        $task->delete();

        $this->schema .= /** @lang GraphQL */ '
        type Task {
            id: ID!
            name: String
        }

        type Mutation {
            forceDeleteTasks(id: ID! @whereKey): Task!
                @can(ability: "delete", query: true)
                @forceDelete
                # The order has to be like this, otherwise @forceDelete will throw
                @softDeletes
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            forceDeleteTasks(id: 1, trashed: WITH) {
                name
            }
        }
        ')->assertJsonCount(1, 'data.forceDeleteTasks');

        $this->assertCount(0, Task::withTrashed()->get());
    }

    public function testRejectsUsingDirectiveWithNonSoftDeleteModels(): void
    {
        $this->expectExceptionMessage(ForceDeleteDirective::MODEL_NOT_USING_SOFT_DELETES);
        $this->buildSchema(/** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Query {
            deleteUser(id: ID! @whereKey): User @forceDelete
        }
        ');
    }
}
