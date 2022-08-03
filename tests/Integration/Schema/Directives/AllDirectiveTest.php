<?php

namespace Tests\Integration\Schema\Directives;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Mockery;
use Tests\DBTestCase;
use Tests\TestsScoutEngine;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;

final class AllDirectiveTest extends DBTestCase
{
    use TestsScoutEngine;

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

    public function testSpecifyCustomBuilderForScoutBuilder(): void
    {
        $this->setUpScoutEngine();

        /** @var \Tests\Utils\Models\Post $postA */
        $postA = factory(Post::class)->create([
            'title' => 'great title',
        ]);
        /** @var \Tests\Utils\Models\Post $postB */
        $postB = factory(Post::class)->create([
            'title' => 'Really great title',
        ]);
        factory(Post::class)->create([
            'title' => 'bad title',
        ]);

        $this->engine->shouldReceive('map')
            ->andReturn(
                new EloquentCollection([$postA, $postB])
            )
            ->once();

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        type Post {
            id: ID!
        }

        type Query {
            posts: [Post!]! @all(builder: "{$this->qualifyTestResolver('builderForScoutBuilder')}")
        }
GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ '
        {
            posts {
                id
            }
        }
        ')->assertJson([
            'data' => [
                'posts' => [
                    [
                        'id' => '1',
                    ],
                    [
                        'id' => '2',
                    ],
                ],
            ],
        ]);
    }

    public function testSpecifyCustomBuilderForScoutBuilderWithScoutDirective(): void
    {
        $this->setUpScoutEngine();

        /** @var \Mockery\MockInterface&\Laravel\Scout\Builder $builder */
        $builder = Mockery::mock(Post::search())->makePartial();
        app()->bind(\Laravel\Scout\Builder::class, function () use ($builder) {
            return $builder;
        });

        /** @var \Tests\Utils\Models\Post $postA */
        $postA = factory(Post::class)->create([
            'title' => 'great title',
            'task_id' => 1,
        ]);
        factory(Post::class)->create([
            'title' => 'Really great title',
            'task_id' => 2,
        ]);
        factory(Post::class)->create([
            'title' => 'bad title',
            'task_id' => 3,
        ]);

        $this->engine->shouldReceive('map')
            ->andReturn(
                new EloquentCollection([$postA])
            )
            ->once();

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        type Post {
            id: ID!
        }

        type Query {
            posts(
                task: ID! @eq(key: "task_id")
            ): [Post!]!
                @all(builder: "{$this->qualifyTestResolver('builderForScoutBuilder')}")
        }
GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ '
        {
            posts(task: "1") {
                id
            }
        }
        ')->assertJson([
            'data' => [
                'posts' => [
                    [
                        'id' => '1',
                    ],
                ],
            ],
        ]);

        // Ensure `@eq` directive has been applied on scout builder instance
        $builder->shouldHaveReceived('where')
            ->with(
                'task_id',
                '1'
            );
    }

    public function builder(): Builder
    {
        return User::orderBy('id', 'DESC');
    }

    public function builderForRelation(User $parent): Relation
    {
        return $parent->posts()->orderBy('id', 'DESC');
    }

    public function builderForScoutBuilder(): \Laravel\Scout\Builder
    {
        return Post::search('great title');
    }
}
