<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class AllDirectiveTest extends DBTestCase
{
    /**
     * @test
     */
    public function itCanGetAllModelsAsRootField(): void
    {
        factory(User::class, 2)->create();

        $this->schema = '
        type User {
            id: ID!
            name: String!
        }
        
        type Query {
            users: [User!]! @all(model: "User")
        }
        ';

        $this->graphQL('
        {
            users {
                id
                name
            }
        }
        ')->assertJsonCount(2, 'data.users');
    }

    /**
     * @test
     */
    public function itCanGetAllAsNestedField(): void
    {
        factory(Post::class, 2)->create([
            // Do not create those, as they would create more users
            'task_id' => 1,
        ]);

        $this->schema = '
        type User {
            posts: [Post!]! @all
        }

        type Post {
            id: ID!
        }

        type Query {
            users: [User!]! @all
        }
        ';

        $this->graphQL('
        {
            users {
                posts {
                    id
                }
            }
        }
        ')->assertJson([
            'data' => [
                'users' => [
                    [
                        'posts' => [
                            [
                                'id' => '1',
                            ],
                            [
                                'id' => '2',
                            ],
                        ],
                    ],
                    [
                        'posts' => [
                            [
                                'id' => '1',
                            ],
                            [
                                'id' => '2',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function itCanGetAllModelsFiltered(): void
    {
        $users = factory(User::class, 3)->create();
        $userName = $users->first()->name;

        $this->schema = '
        type User {
            id: ID!
            name: String!
        }
        
        type Query {
            users(name: String @neq): [User!]! @all
        }
        ';

        $this->graphQL('
        {
            users(name: "'.$userName.'") {
                id
                name
            }
        }
        ')->assertJsonCount(2, 'data.users');
    }

    /**
     * @test
     */
    public function itCanApplyTrashedArgument(): void
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
            tasks(trashed: Trash): [Task!]! @all
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
                    ]
                ]
            ]
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
    }


    /**
     * @test
     */
    public function itCanFetchWithoutTrashedOnMissedTrashedArgument(): void
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
            tasks(trashed: Trash): [Task!]! @all
            tasks2: [Task!]! @all
        }
        ';

        $this->graphQL('
        {
            tasks {
                id
                name
            }
        }
        ')->assertJsonCount(2, 'data.tasks');

        $this->graphQL('
        {
            tasks2 {
                id
                name
            }
        }
        ')->assertJsonCount(2, 'data.tasks2');
    }

}
