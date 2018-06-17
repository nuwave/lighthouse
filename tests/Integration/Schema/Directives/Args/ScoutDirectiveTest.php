<?php


namespace Tests\Integration\Schema\Directives\Args;


use Illuminate\Foundation\Testing\RefreshDatabase;

use Laravel\Scout\Builder;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\Engine;
use Laravel\Scout\Engines\NullEngine;
use Mockery;
use Mockery\Mock;
use Tests\DBTestCase;
use Tests\Utils\Models\Comment;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;

class ScoutDirectiveTest extends DBTestCase
{
    use RefreshDatabase;

    /** @var Mockery\MockInterface */
    protected $engineManager;

    /** @var Mock */
    protected $engine;

    protected function setUp()
    {
        parent::setUp();
        $this->engineManager = Mockery::mock();
        $this->engine = Mockery::mock(NullEngine::class)->makePartial();

        $this->app->singleton(EngineManager::class, function ($app) {
            return $this->engineManager;
        });

        $this->engineManager->shouldReceive('engine')
            ->andReturn($this->engine);
    }


    /** @test */
    public function canSearch()
    {

        $postA = factory(Post::class)->create([
            'title' => 'great title'
        ]);
        $postB = factory(Post::class)->create([
            'title' => 'Really great title'
        ]);
        $postC = factory(Post::class)->create([
            'title' => 'bad title'
        ]);

        $this->engine->shouldReceive("map")->andReturn(collect([$postA, $postB]));

        $schema = '     
        type Post {
            id: ID!
            title: String!
        }
  
        type Query {
            posts(search: String @search): [Post!]! @paginate(type: "paginator" model: "Post")
        }
        ';

        $query = '{
            posts(count: 10 search: "great") {
                data {
                    id
                    title
                }
            }
        }';

        $result = $this->execute($schema, $query, true);
        $this->assertEquals($postA->id, $result->data['posts']['data'][0]['id']);
        $this->assertEquals($postB->id, $result->data['posts']['data'][1]['id']);
    }

    /** @test */
    public function canSearchWithCustomIndex()
    {

        $postA = factory(Post::class)->create([
            'title' => 'great title'
        ]);
        $postB = factory(Post::class)->create([
            'title' => 'Really great title'
        ]);
        $postC = factory(Post::class)->create([
            'title' => 'bad title'
        ]);

        $this->engine->shouldReceive("map")->andReturn(collect([$postA, $postB]))->once();

        $this->engine->shouldReceive('paginate')->with(
                Mockery::on(function ($argument) {
                    return $argument->index == "my.index";
                }), Mockery::any(), Mockery::any()
            )
            ->andReturn(collect([$postA, $postB]))
            ->once();

        $schema = '     
        type Post {
            id: ID!
            title: String!
        }
  
        type Query {
            posts(search: String @search(within: "my.index")): [Post!]! @paginate(type: "paginator" model: "Post")
        }
        ';

        $query = '{
            posts(count: 10 search: "great") {
                data {
                    id
                    title
                }
            }
        }';

        $result = $this->execute($schema, $query, true);
        $this->assertEquals($postA->id, $result->data['posts']['data'][0]['id']);
        $this->assertEquals($postB->id, $result->data['posts']['data'][1]['id']);
    }

}