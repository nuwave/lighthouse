<?php

namespace Tests\Integration\Schema\Directives;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;

final class AllDirectiveTest extends DBTestCase
{
    public function testGetAllModelsAsRootField(): void
    {
        $count = 2;
        factory(User::class, $count)->create();

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Query {
            users: [User!]! @all
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            users {
                id
            }
        }
        ')->assertJsonCount($count, 'data.users');
    }

    public function testExplicitModelName(): void
    {
        $count = 2;
        factory(User::class, $count)->create();

        $this->schema = /** @lang GraphQL */ '
        type Foo {
            id: ID!
        }

        type Query {
            foos: [Foo!]! @all(model: "User")
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foos {
                id
            }
        }
        ')->assertJsonCount($count, 'data.foos');
    }

    public function testRenamedModelWithModelDirective(): void
    {
        $count = 2;
        factory(User::class, $count)->create();

        $this->schema = /** @lang GraphQL */ '
        type Foo @model(class: "User") {
            id: ID!
        }

        type Query {
            foos: [Foo!]! @all
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foos {
                id
            }
        }
        ')->assertJsonCount($count, 'data.foos');
    }

    public function testGetAllAsNestedField(): void
    {
        factory(Post::class, 2)->create([
            // Do not create those, as they would create more users
            'task_id' => 1,
        ]);

        $this->schema = /** @lang GraphQL */ '
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

        $this->schema = /** @lang GraphQL */ '
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
            users: [User!]! @all(builder: "' . $this->qualifyTestResolver('builder') . '")
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

    public function testSpecifyCustomBuilderForRelation(): void
    {
        $user = factory(User::class)->create();
        assert($user instanceof User);

        $posts = factory(Post::class, 2)->make();
        $user->posts()->saveMany($posts);

        $this->schema = /** @lang GraphQL */ '
        type Post {
            id: ID!
        }

        type User {
            id: ID!
            posts: [Post!]! @all(builder: "' . $this->qualifyTestResolver('builderForRelation') . '")
        }

        type Query {
            user(id: ID! @eq): User @find
        }
        ';

        // The custom builder is supposed to change the sort order
        $this->graphQL(/** @lang GraphQL */ "
        {
            user(id: {$user->id}) {
                posts {
                    id
                }
            }
        }
        ")->assertJson([
            'data' => [
                'user' => [
                    'posts' => [
                        [
                            'id' => '2',
                        ],
                        [
                            'id' => '1',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function builder(): Builder
    {
        return User::orderBy('id', 'DESC');
    }

    public function builderForRelation(User $parent): Relation
    {
        return $parent->posts()->orderBy('id', 'DESC');
    }
}
