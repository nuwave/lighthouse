<?php declare(strict_types=1);

namespace Tests\Integration\SoftDeletes;

use Nuwave\Lighthouse\SoftDeletes\TrashedDirective;
use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

final class SoftDeletesAndTrashedDirectiveTest extends DBTestCase
{
    public function testWithAllDirective(): void
    {
        $tasks = factory(Task::class, 3)->create();
        $taskToRemove = $tasks[2];
        $taskToRemove->delete();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Task {
            id: ID!
        }

        type Query {
            tasks: [Task!]! @all @softDeletes
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            tasks(trashed: ONLY) {
                id
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'tasks' => [
                    [
                        'id' => $taskToRemove->id,
                    ],
                ],
            ],
        ]);

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            tasks(trashed: WITH) {
                id
            }
        }
        GRAPHQL)->assertJsonCount(3, 'data.tasks');

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            tasks(trashed: WITHOUT) {
                id
            }
        }
        GRAPHQL)->assertJsonCount(2, 'data.tasks');

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            tasks {
                id
            }
        }
        GRAPHQL)->assertJsonCount(2, 'data.tasks');
    }

    public function testNullDoesNothing(): void
    {
        $tasks = factory(Task::class, 2)->create();

        $taskToRemove = $tasks[0];
        $taskToRemove->delete();

        $leftoverTask = $tasks[1];

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Task {
            id: ID!
        }

        type Query {
            tasks: [Task!]! @all @softDeletes
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            tasks(trashed: null) {
                id
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'tasks' => [
                    [
                        'id' => $leftoverTask->id,
                    ],
                ],
            ],
        ]);
    }

    public function testWithFindDirective(): void
    {
        /** @var Task $taskToRemove */
        $taskToRemove = factory(Task::class)->create();
        $taskToRemove->delete();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Task {
            id: ID!
        }

        type Query {
            task(id: ID! @eq): Task @find @softDeletes
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            task(id: 1, trashed: ONLY) {
                id
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'task' => [
                    'id' => $taskToRemove->id,
                ],
            ],
        ]);

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            task(id: 1, trashed: WITH) {
                id
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'task' => [
                    'id' => $taskToRemove->id,
                ],
            ],
        ]);

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            task(id: 1, trashed: WITHOUT) {
                id
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'task' => null,
            ],
        ]);

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            task(id: 1) {
                id
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'task' => null,
            ],
        ]);
    }

    public function testWithPaginateDirective(): void
    {
        $tasks = factory(Task::class, 3)->create();
        /** @var Task $taskToRemove */
        $taskToRemove = $tasks[2];
        $taskToRemove->delete();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Task {
            id: ID!
        }

        type Query {
            tasks: [Task!]! @paginate @softDeletes
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            tasks(first: 10, trashed: ONLY) {
                data {
                    id
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'tasks' => [
                    'data' => [
                        [
                            'id' => $taskToRemove->id,
                        ],
                    ],
                ],
            ],
        ]);

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            tasks(first: 10, trashed: WITH) {
                data {
                    id
                }
            }
        }
        GRAPHQL)->assertJsonCount(3, 'data.tasks.data');

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            tasks(first: 10, trashed: WITHOUT) {
                data {
                    id
                }
            }
        }
        GRAPHQL)->assertJsonCount(2, 'data.tasks.data');

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            tasks(first: 10) {
                data {
                    id
                }
            }
        }
        GRAPHQL)->assertJsonCount(2, 'data.tasks.data');
    }

    public function testNested(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();

        /** @var Task $taskToRemove */
        $taskToRemove = factory(Task::class)->make();
        $taskToRemove->user()->associate($user);
        $taskToRemove->save();
        $taskToRemove->delete();

        $user->tasks()->saveMany(
            factory(Task::class, 2)->make(),
        );

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Task {
            id: ID!
        }

        type User {
            id: ID!
            tasks: [Task!]! @hasMany @softDeletes
        }

        type Query {
            users: [User!]! @all
            usersPaginated: [User!]! @paginate
            user(id: ID! @eq): User @find
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            users {
                tasks(trashed: ONLY) {
                    id
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'users' => [
                    [
                        'tasks' => [
                            [
                                'id' => $taskToRemove->id,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            usersPaginated(first: 10) {
                data {
                    tasks(trashed: ONLY) {
                        id
                    }
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'usersPaginated' => [
                    'data' => [
                        [
                            'tasks' => [
                                [
                                    'id' => $taskToRemove->id,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            user(id: 1) {
                tasks(trashed: ONLY) {
                    id
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'user' => [
                    'tasks' => [
                        [
                            'id' => $taskToRemove->id,
                        ],
                    ],
                ],
            ],
        ]);

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                users {
                    tasksWith: tasks(trashed: WITH) {
                        id
                    }
                    tasksWithout: tasks(trashed: WITHOUT) {
                        id
                    }
                    tasksSimple: tasks {
                        id
                    }
                }
            }
            GRAPHQL)
            ->assertJsonCount(3, 'data.users.0.tasksWith')
            ->assertJsonCount(2, 'data.users.0.tasksWithout')
            ->assertJsonCount(2, 'data.users.0.tasksSimple');

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                usersPaginated(first: 10) {
                    data {
                        tasksWith: tasks(trashed: WITH) {
                            id
                        }
                        tasksWithout: tasks(trashed: WITHOUT) {
                            id
                        }
                        tasksSimple: tasks {
                            id
                        }
                    }
                }
            }
            GRAPHQL)
            ->assertJsonCount(3, 'data.usersPaginated.data.0.tasksWith')
            ->assertJsonCount(2, 'data.usersPaginated.data.0.tasksWithout')
            ->assertJsonCount(2, 'data.usersPaginated.data.0.tasksSimple');

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                user(id: 1) {
                    tasksWith: tasks(trashed: WITH) {
                        id
                    }
                    tasksWithout: tasks(trashed: WITHOUT) {
                        id
                    }
                    tasksSimple: tasks {
                        id
                    }
                }
            }
            GRAPHQL)
            ->assertJsonCount(3, 'data.user.tasksWith')
            ->assertJsonCount(2, 'data.user.tasksWithout')
            ->assertJsonCount(2, 'data.user.tasksSimple');
    }

    public function testThrowsIfModelDoesNotSupportSoftDeletesTrashed(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            trashed(trashed: Trashed @trashed): [User!]! @all
        }

        type User {
            id: ID
        }
        GRAPHQL;

        $this->expectExceptionMessage(TrashedDirective::MODEL_MUST_USE_SOFT_DELETES);
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            trashed(trashed: WITH) {
                id
            }
        }
        GRAPHQL);
    }

    public function testThrowsIfModelDoesNotSupportSoftDeletes(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            softDeletes: [User!]! @all @softDeletes
        }

        type User {
            id: ID
        }
        GRAPHQL;

        $this->expectExceptionMessage(TrashedDirective::MODEL_MUST_USE_SOFT_DELETES);
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            softDeletes(trashed: WITH) {
                id
            }
        }
        GRAPHQL);
    }
}
