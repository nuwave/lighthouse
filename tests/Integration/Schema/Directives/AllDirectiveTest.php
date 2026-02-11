<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Laravel\Scout\Builder as ScoutBuilder;
use Tests\DBTestCase;
use Tests\TestsScoutEngine;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

final class AllDirectiveTest extends DBTestCase
{
    use TestsScoutEngine;

    public const LIMIT_FROM_CUSTOM_SCOUT_BUILDER = 321;

    public function testGetAllModelsAsRootField(): void
    {
        $count = 2;
        factory(User::class, $count)->create();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
        }

        type Query {
            users: [User!]! @all
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            users {
                id
            }
        }
        GRAPHQL)->assertJsonCount($count, 'data.users');
    }

    public function testExplicitModelName(): void
    {
        $count = 2;
        factory(User::class, $count)->create();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Foo {
            id: ID!
        }

        type Query {
            foos: [Foo!]! @all(model: "User")
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foos {
                id
            }
        }
        GRAPHQL)->assertJsonCount($count, 'data.foos');
    }

    public function testRenamedModelWithModelDirective(): void
    {
        $count = 2;
        factory(User::class, $count)->create();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Foo @model(class: "User") {
            id: ID!
        }

        type Query {
            foos: [Foo!]! @all
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foos {
                id
            }
        }
        GRAPHQL)->assertJsonCount($count, 'data.foos');
    }

    public function testGetAllAsNestedField(): void
    {
        $task = factory(Task::class)->create();

        factory(Post::class, 2)
            ->create()
            ->each(static function (Post $post) use ($task): void {
                // Do not create those, as they would create more users.
                $post->task()->associate($task);
                $post->save();
            });

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            posts: [Post!]! @all
        }

        type Post {
            id: ID!
        }

        type Query {
            users: [User!]! @all
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            users {
                posts {
                    id
                }
            }
        }
        GRAPHQL)->assertJson([
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

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users(name: String @neq): [User!]! @all
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        {
            users(name: "{$userName}") {
                id
                name
            }
        }
        GRAPHQL)->assertJsonCount(2, 'data.users');
    }

    public function testSpecifyCustomBuilder(): void
    {
        factory(User::class, 2)->create();

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users: [User!]! @all(builder: "{$this->qualifyTestResolver('builder')}")
        }
        GRAPHQL;

        // The custom builder is supposed to change the sort order
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            users {
                id
            }
        }
        GRAPHQL)->assertJson([
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
        $this->assertInstanceOf(User::class, $user);

        $posts = factory(Post::class, 2)->make();
        $user->posts()->saveMany($posts);

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        type Post {
            id: ID!
        }

        type User {
            id: ID!
            posts: [Post!]! @all(builder: "{$this->qualifyTestResolver('builderForRelation')}")
        }

        type Query {
            user(id: ID! @eq): User @find
        }
        GRAPHQL;

        // The custom builder is supposed to change the sort order
        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        {
            user(id: {$user->id}) {
                posts {
                    id
                }
            }
        }
        GRAPHQL)->assertJson([
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

        $post = factory(Post::class)->create();
        $this->assertInstanceOf(Post::class, $post);

        $this->engine->shouldReceive('map')
            ->withArgs(static fn (ScoutBuilder $builder): bool => $builder->wheres === ['id' => "{$post->id}"]
                && $builder->limit === self::LIMIT_FROM_CUSTOM_SCOUT_BUILDER)
            ->andReturn(new EloquentCollection([$post]))
            ->once();

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        type Post {
            id: ID!
        }

        type Query {
            posts(
                id: ID! @eq
            ): [Post!]! @all(builder: "{$this->qualifyTestResolver('builderForScoutBuilder')}")
        }
GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($id: ID!) {
            posts(id: $id) {
                id
            }
        }
        GRAPHQL, [
            'id' => $post->id,
        ])->assertJson([
            'data' => [
                'posts' => [
                    [
                        'id' => "{$post->id}",
                    ],
                ],
            ],
        ]);
    }

    /** @return \Illuminate\Database\Eloquent\Builder<\Tests\Utils\Models\User> */
    public static function builder(): EloquentBuilder
    {
        return User::query()
            ->orderByDesc('id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\Tests\Utils\Models\Post, \Tests\Utils\Models\User> */
    public static function builderForRelation(User $parent): Relation
    {
        return $parent->posts()
            ->orderByDesc('id');
    }

    public static function builderForScoutBuilder(): ScoutBuilder
    {
        return Post::search('great title')
            ->take(self::LIMIT_FROM_CUSTOM_SCOUT_BUILDER);
    }
}
