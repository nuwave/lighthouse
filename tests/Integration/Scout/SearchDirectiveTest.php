<?php

namespace Tests\Integration\Scout;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Laravel\Scout\Builder as ScoutBuilder;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\NullEngine;
use Mockery;
use Mockery\MockInterface;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Scout\ScoutException;
use Tests\DBTestCase;
use Tests\Utils\Models\Post;

class SearchDirectiveTest extends DBTestCase
{
    /**
     * @var \Mockery\MockInterface&\Laravel\Scout\EngineManager
     */
    protected $engineManager;

    /**
     * @var \Mockery\MockInterface&\Laravel\Scout\Engines\NullEngine
     */
    protected $engine;

    public function setUp(): void
    {
        parent::setUp();

        $this->engineManager = Mockery::mock(EngineManager::class);
        $this->engine = Mockery
            ::mock(NullEngine::class)
            ->makePartial();

        $this->app->singleton(EngineManager::class, function (): MockInterface {
            return $this->engineManager;
        });

        $this->engineManager
            ->shouldReceive('engine')
            ->andReturn($this->engine);
    }

    public function testSearch(): void
    {
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

        $this->engine
            ->shouldReceive('map')
            ->andReturn(
                new EloquentCollection([$postA, $postB])
            );

        $this->schema = /** @lang GraphQL */ '
        type Post {
            id: ID!
            title: String!
        }

        type Query {
            posts(
                search: String @search
            ): [Post!]! @all
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            posts(search: "great") {
                id
                title
            }
        }
        ')->assertJson([
            'data' => [
                'posts' => [
                    [
                        'id' => $postA->id,
                    ],
                    [
                        'id' => $postB->id,
                    ],
                ],
            ],
        ]);
    }

    public function testSearchWithEq(): void
    {
        $id = 1;

        $this->engine
            ->shouldReceive('map')
            ->withArgs(function (ScoutBuilder $builder) use ($id): bool {
                return $builder->wheres === ['id' => $id];
            })
            ->andReturn(new EloquentCollection())
            ->once();

        $this->schema = /** @lang GraphQL */ '
        type Post {
            id: Int!
        }

        type Query {
            posts(
                id: Int @eq
                search: String @search
            ): [Post!]! @all
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        query ($id: Int) {
            posts(id: $id, search: "great") {
                id
            }
        }
        ', [
            'id' => $id,
        ])->assertJson([
            'data' => [
                'posts' => [],
            ],
        ]);
    }

    public function testSearchWithTrashed(): void
    {
        $this->engine
            ->shouldReceive('map')
            ->withArgs(function (ScoutBuilder $builder): bool {
                return $builder->wheres === ['__soft_deleted' => 1];
            })
            ->andReturn(new EloquentCollection())
            ->once();

        $this->schema = /** @lang GraphQL */ '
        type Post {
            id: Int!
        }

        type Query {
            posts(
                id: Int @eq
                search: String @search
            ): [Post!]! @all @softDeletes
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            posts(search: "foo", trashed: ONLY) {
                id
            }
        }
        ')->assertJson([
            'data' => [
                'posts' => [],
            ],
        ]);
    }

    public function testCanSearchWithinCustomIndex(): void
    {
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

        $myIndex = 'my.index';

        $this->engine
            ->shouldReceive('map')
            ->withArgs(function (ScoutBuilder $builder) use ($myIndex): bool {
                return $builder->index === $myIndex;
            })
            ->andReturn(
                new EloquentCollection([$postA, $postB])
            )
            ->once();

        $this->schema = /** @lang GraphQL */ "
        type Post {
            id: ID!
        }

        type Query {
            posts(
                search: String @search(within: \"{$myIndex}\")
            ): [Post!]! @all
        }
        ";

        $this->graphQL(/** @lang GraphQL */ '
        {
            posts(search: "great") {
                id
            }
        }
        ')->assertJson([
            'data' => [
                'posts' => [
                    [
                        'id' => $postA->id,
                    ],
                    [
                        'id' => $postB->id,
                    ],
                ],
            ],
        ]);
    }

    public function testWithinMustBeString(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Post {
            id: ID!
        }

        type Query {
            posts(
                search: String @search(within: 123)
            ): [Post!]! @all
        }
        ';

        $this->expectException(DefinitionException::class);

        $this->graphQL(/** @lang GraphQL */ '
        {
            posts(search: "great") {
                id
            }
        }
        ');
    }

    public function testMultipleSearchesAreNotAllowed(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Post {
            id: ID!
        }

        type Query {
            posts(
                first: String @search
                second: String @search
            ): [Post!]! @all
        }
        ';

        $this->expectException(ScoutException::class);

        $this->graphQL(/** @lang GraphQL */ '
        {
            posts(first: "great", second: "nope") {
                id
            }
        }
        ');
    }

    public function testIncompatibleArgBuildersAreNotAllowed(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Post {
            id: ID!
        }

        type Query {
            posts(
                search: String @search
                nope: String @neq
            ): [Post!]! @all
        }
        ';

        $this->expectException(ScoutException::class);

        $this->graphQL(/** @lang GraphQL */ '
        {
            posts(search: "great", nope: "nope") {
                id
            }
        }
        ');
    }

    public function testModelMustBeSearchable(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Task {
            id: ID!
        }

        type Query {
            tasks(
                search: String @search
            ): [Task!]! @all
        }
        ';

        $this->expectException(ScoutException::class);

        $this->graphQL(/** @lang GraphQL */ '
        {
            tasks(search: "great") {
                id
            }
        }
        ');
    }

    public function testHandlesScoutBuilderPaginationArguments(): void
    {
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

        $this->engine->shouldReceive('paginate')
            ->with(
                Mockery::any(),
                Mockery::any(),
                Mockery::not('page')
            )
            ->andReturn(new EloquentCollection([$postA, $postB]))
            ->once();

        $this->schema = /** @lang GraphQL */ '
        type Post {
            id: ID!
        }

        type Query {
            posts(
                search: String @search
            ): [Post!]! @paginate
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            posts(first: 10, search: "great") {
                data {
                    id
                }
            }
        }
        ')->assertJson([
            'data' => [
                'posts' => [
                    'data' => [
                        [
                            'id' => "$postA->id",
                        ],
                        [
                            'id' => "$postB->id",
                        ],
                    ],
                ],
            ],
        ]);
    }
}
