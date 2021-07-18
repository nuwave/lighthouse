<?php

namespace Tests\Integration\Schema\Directives;

use Illuminate\Database\Eloquent\Builder;
use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;

class AllDirectiveTest extends DBTestCase
{
    public function testGetAllModelsAsRootField(): void
    {
        factory(User::class, 2)->create();

        $this->schema = /** @lang GraphQL */'
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users: [User!]! @all(model: "User")
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            users {
                id
                name
            }
        }
        ')->assertJsonCount(2, 'data.users');
    }

    public function testGetAllAsNestedField(): void
    {
        factory(Post::class, 2)->create([
            // Do not create those, as they would create more users
            'task_id' => 1,
        ]);

        $this->schema = /** @lang GraphQL */'
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

        $this->graphQL(/** @lang GraphQL */ '
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

    public function testGetAllModelsFiltered(): void
    {
        $users = factory(User::class, 3)->create();
        $userName = $users->first()->name;

        $this->schema = /** @lang GraphQL */'
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users(name: String @neq): [User!]! @all
        }
        ';

        $this->graphQL(/** @lang GraphQL */ "
        {
            users(name: \"{$userName}\") {
                id
                name
            }
        }
        ")->assertJsonCount(2, 'data.users');
    }

    public function testSpecifyCustomBuilder(): void
    {
        factory(User::class, 2)->create();

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users: [User!]! @all(builder: "'.$this->qualifyTestResolver('builder').'")
        }
        ';

        // The custom builder is supposed to change the sort order
        $this->graphQL(/** @lang GraphQL */ '
        {
            users {
                id
            }
        }
        ')->assertJson([
            'data' => [
                'users' => [
                    [
                        'id' => '2',
                    ],
                    [
                        'id' => '1',
                    ],
                ],
            ],
        ]);
    }

    public function builder(): Builder
    {
        return User::orderBy('id', 'DESC');
    }
}
