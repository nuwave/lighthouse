<?php

namespace Tests\Integration\SoftDeletes;

use Nuwave\Lighthouse\SoftDeletes\TrashedDirective;
use Tests\DBTestCase;
use Tests\Utils\Models\Task;

class SoftDeletesAndTrashedDirectiveTest extends DBTestCase
{
    public function testCanBeUsedWithAllDirective(): void
    {
        $tasks = factory(Task::class, 3)->create();
        $taskToRemove = $tasks[2];
        $taskToRemove->delete();

        $this->schema = '
        type Task {
            id: ID!
            name: String!
        }
        
        type Query {
            tasks: [Task!]! @all @softDeletes
        }
        ';

        $this->graphQL('
        {
            tasks(trashed: ONLY) {
                id
                name
            }
        }
        ')->assertJson([
            'data' => [
                'tasks' => [
                    [
                        'id'   => $taskToRemove->id,
                        'name' => $taskToRemove->name,
                    ],
                ],
            ],
        ]);

        $this->graphQL('
        {
            tasks(trashed: WITH) {
                id
                name
            }
        }
        ')->assertJsonCount(3, 'data.tasks');

        $this->graphQL('
        {
            tasks(trashed: WITHOUT) {
                id
                name
            }
        }
        ')->assertJsonCount(2, 'data.tasks');

        $this->graphQL('
        {
            tasks {
                id
                name
            }
        }
        ')->assertJsonCount(2, 'data.tasks');
    }

    public function testCanBeUsedWithFindDirective(): void
    {
        $taskToRemove = factory(Task::class)->create();
        $taskToRemove->delete();

        $this->schema = '
        type Task {
            id: ID!
            name: String!
        }
        
        type Query {
            task(id: ID! @eq): Task @find @softDeletes
        }
        ';

        $this->graphQL('
        {
            task(id: 1, trashed: ONLY) {
                id
                name
            }
        }
        ')->assertJson([
            'data' => [
                'task' => [
                    'id'   => $taskToRemove->id,
                    'name' => $taskToRemove->name,
                ],
            ],
        ]);

        $this->graphQL('
        {
            task(id: 1, trashed: WITH) {
                id
                name
            }
        }
        ')->assertJson([
            'data' => [
                'task' => [
                    'id'   => $taskToRemove->id,
                    'name' => $taskToRemove->name,
                ],
            ],
        ]);

        $this->graphQL('
        {
            task(id: 1, trashed: WITHOUT) {
                id
                name
            }
        }
        ')->assertJson([
            'data' => [
                'task' => null,
            ],
        ]);

        $this->graphQL('
        {
            task(id: 1) {
                id
                name
            }
        }
        ')->assertJson([
            'data' => [
                'task' => null,
            ],
        ]);
    }

    public function testCanCanBeUsedWIthPaginateDirective(): void
    {
        $tasks = factory(Task::class, 3)->create();
        $taskToRemove = $tasks[2];
        $taskToRemove->delete();

        $this->schema = '
        type Task {
            id: ID!
            name: String!
        }
        
        type Query {
            tasks: [Task!]! @paginate @softDeletes
        }
        ';

        $this->graphQL('
        {
            tasks(first: 10, trashed: ONLY) {
                data {
                    id
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'tasks' => [
                    'data' => [
                        [
                            'id'   => $taskToRemove->id,
                            'name' => $taskToRemove->name,
                        ],
                    ],
                ],
            ],
        ]);

        $this->graphQL('
        {
            tasks(first: 10, trashed: WITH) {
                data {
                    id
                    name
                }
            }
        }
        ')->assertJsonCount(3, 'data.tasks.data');

        $this->graphQL('
        {
            tasks(first: 10, trashed: WITHOUT) {
                data {
                    id
                    name
                }
            }
        }
        ')->assertJsonCount(2, 'data.tasks.data');

        $this->graphQL('
        {
            tasks(first: 10) {
                data {
                    id
                    name
                }
            }
        }
        ')->assertJsonCount(2, 'data.tasks.data');
    }

    public function testCanBeUsedNested(): void
    {
        $taskToRemove = factory(Task::class)->create();
        factory(Task::class, 2)->create(['user_id' => $taskToRemove->user->id]);
        $taskToRemove->delete();

        $this->schema = '
        type Task {
            id: ID!
            name: String!
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
        ';

        $this->graphQL('
        {
            users {
                tasks(trashed: ONLY) {
                    id
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'users' => [
                    [
                        'tasks' => [
                            [
                                'id'   => $taskToRemove->id,
                                'name' => $taskToRemove->name,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->graphQL('
        {
            usersPaginated(first: 10) {
                data {
                    tasks(trashed: ONLY) {
                        id
                        name
                    }
                }
            }
        }
        ')->assertJson([
            'data' => [
                'usersPaginated' => [
                    'data' => [
                        [
                            'tasks' => [
                                [
                                    'id'   => $taskToRemove->id,
                                    'name' => $taskToRemove->name,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->graphQL('
        {
            user(id: 1) {
                tasks(trashed: ONLY) {
                    id
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'tasks' => [
                        [
                            'id'   => $taskToRemove->id,
                            'name' => $taskToRemove->name,
                        ],
                    ],
                ],
            ],
        ]);

        $this->graphQL('
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
        ')
             ->assertJsonCount(3, 'data.users.0.tasksWith')
             ->assertJsonCount(2, 'data.users.0.tasksWithout')
             ->assertJsonCount(2, 'data.users.0.tasksSimple');

        $this->graphQL('
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
        ')
             ->assertJsonCount(3, 'data.usersPaginated.data.0.tasksWith')
             ->assertJsonCount(2, 'data.usersPaginated.data.0.tasksWithout')
             ->assertJsonCount(2, 'data.usersPaginated.data.0.tasksSimple');

        $this->graphQL('
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
        ')
             ->assertJsonCount(3, 'data.user.tasksWith')
             ->assertJsonCount(2, 'data.user.tasksWithout')
             ->assertJsonCount(2, 'data.user.tasksSimple');
    }

    public function testThrowsIfModelDoesNotSupportSoftDeletesTrashed(): void
    {
        $this->schema = '
        type Query {
            trashed(trashed: Trashed @trashed): [User!]! @all
        }
        
        type User {
            id: ID
        }
        ';

        $this->expectExceptionMessage(TrashedDirective::MODEL_MUST_USE_SOFT_DELETES);
        $this->graphQL('
        {
            trashed(trashed: WITH) {
                id
            }
        }
        ');
    }

    public function testThrowsIfModelDoesNotSupportSoftDeletes(): void
    {
        $this->schema = '
        type Query {
            softDeletes: [User!]! @all @softDeletes
        }
        
        type User {
            id: ID
        }
        ';

        $this->expectExceptionMessage(TrashedDirective::MODEL_MUST_USE_SOFT_DELETES);
        $this->graphQL('
        {
            softDeletes(trashed: WITH) {
                id
            }
        }
        ');
    }
}
