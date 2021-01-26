<?php

namespace Tests\Integration\Schema\Directives;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\NullEngine;
use Mockery;
use Mockery\MockInterface;
use Tests\DBTestCase;
use Tests\Utils\Models\Post;

class SearchDirectiveTest extends DBTestCase
{
    /**
     * @var \Mockery\MockInterface
     */
    protected $engineManager;

    /**
     * @var \Mockery\MockInterface
     */
    protected $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engineManager = Mockery::mock();
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
            'title' => 'Really bad title',
        ]);
        /** @var \Tests\Utils\Models\Post $postC */
        $postC = factory(Post::class)->create([
            'title' => 'another great title',
        ]);

        $this->engine
            ->shouldReceive('map')
            ->andReturn(
                new EloquentCollection([$postA, $postC])
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
                        'id' => $postC->id,
                    ],
                ],
            ],
        ]);
    }

    public function testCanSearchWithCustomIndex(): void
    {
        /** @var \Tests\Utils\Models\Post $postA */
        $postA = factory(Post::class)->create([
            'title' => 'great title',
        ]);
        /** @var \Tests\Utils\Models\Post $postB */
        $postB = factory(Post::class)->create([
            'title' => 'Really great title',
        ]);
        /** @var \Tests\Utils\Models\Post $postC */
        $postC = factory(Post::class)->create([
            'title' => 'bad title',
        ]);

        $this->engine
            ->shouldReceive('map')
            ->with(
                Mockery::on(
                    function ($argument): bool {
                        return $argument->index === 'my.index';
                    }
                ),
                Mockery::any(),
                Mockery::any()
            )
            ->andReturn(
                new EloquentCollection([$postA, $postB])
            )
            ->once();

        $this->schema = /** @lang GraphQL */ '
        type Post {
            id: ID!
            title: String!
        }

        type Query {
            posts(
                search: String @search(within: "my.index")
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
                        'id' => "$postA->id",
                    ],
                    [
                        'id' => "$postB->id",
                    ],
                ],
            ],
        ]);
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
        /** @var \Tests\Utils\Models\Post $postC */
        $postC = factory(Post::class)->create([
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
            title: String!
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
                    title
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
