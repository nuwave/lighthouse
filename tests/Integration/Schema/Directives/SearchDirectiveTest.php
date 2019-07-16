<?php

namespace Tests\Integration\Schema\Directives;

use Mockery;
use Tests\DBTestCase;
use Mockery\MockInterface;
use Tests\Utils\Models\Post;
use Laravel\Scout\EngineManager;
use Illuminate\Support\Collection;
use Laravel\Scout\Engines\NullEngine;

class SearchDirectiveTest extends DBTestCase
{
    /**
     * @var \Mockery\MockInterface
     */
    protected $engineManager;

    /**
     * @var \Mockery\Mock
     */
    protected $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engineManager = Mockery::mock();
        $this->engine = Mockery::mock(NullEngine::class)->makePartial();

        $this->app->singleton(EngineManager::class, function (): MockInterface {
            return $this->engineManager;
        });

        $this->engineManager->shouldReceive('engine')
            ->andReturn($this->engine);
    }

    /**
     * @test
     */
    public function canSearch(): void
    {
        $postA = factory(Post::class)->create([
            'title' => 'great title',
        ]);
        $postB = factory(Post::class)->create([
            'title' => 'Really bad title',
        ]);
        $postC = factory(Post::class)->create([
            'title' => 'another great title',
        ]);

        $this->engine->shouldReceive('map')->andReturn(new Collection([$postA, $postC]));

        $this->schema = '
        type Post {
            id: ID!
            title: String!
        }

        type Query {
            posts(search: String @search): [Post!]! @paginate(type: "paginator" model: "Post")
        }
        ';

        $this->graphQL('
        {
            posts(first: 10 search: "great") {
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
                            'id' => $postA->id,
                        ],
                        [
                            'id' => $postC->id,
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function canSearchWithCustomIndex(): void
    {
        $postA = factory(Post::class)->create([
            'title' => 'great title',
        ]);
        $postB = factory(Post::class)->create([
            'title' => 'Really great title',
        ]);
        $postC = factory(Post::class)->create([
            'title' => 'bad title',
        ]);

        $this->engine->shouldReceive('map')
            ->andReturn(
                new Collection([$postA, $postB])
            )
            ->once();

        $this->engine->shouldReceive('paginate')
            ->with(
                Mockery::on(
                    function ($argument): bool {
                        return $argument->index === 'my.index';
                    }
                ),
                Mockery::any(),
                Mockery::any()
            )
            ->andReturn(new Collection([$postA, $postB]))
            ->once();

        $this->schema = '
        type Post {
            id: ID!
            title: String!
        }

        type Query {
            posts(search: String @search(within: "my.index")): [Post!]! @paginate(type: "paginator" model: "Post")
        }
        ';

        $this->graphQL('
        {
            posts(first: 10 search: "great") {
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

    /**
     * @test
     */
    public function itHandlesScoutBuilderPaginationArguments(): void
    {
        $postA = factory(Post::class)->create([
            'title' => 'great title',
        ]);
        $postB = factory(Post::class)->create([
            'title' => 'Really great title',
        ]);
        $postC = factory(Post::class)->create([
            'title' => 'bad title',
        ]);

        $this->engine->shouldReceive('map')
            ->andReturn(
                new Collection([$postA, $postB])
            )
            ->once();

        $this->engine->shouldReceive('paginate')
            ->with(
                Mockery::any(),
                Mockery::any(),
                Mockery::not('page')
            )
            ->andReturn(new Collection([$postA, $postB]))
            ->once();

        $this->schema = '
        type Post {
            id: ID!
            title: String!
        }

        type Query {
            posts(search: String @search): [Post!]! @paginate(type: "paginator" model: "Post")
        }
        ';

        $this->graphQL('
        {
            posts(first: 10 search: "great") {
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
