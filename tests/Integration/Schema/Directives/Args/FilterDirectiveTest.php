<?php


namespace Tests\Integration\Schema\Directives\Args;


use GraphQL\Error\Debug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;

class FilterDirectiveTest extends DBTestCase
{
    use RefreshDatabase;

    private $postA, $postB, $postC, $postD, $postE;
    private $userA, $userB;
    private $schema;

    protected function setUp()
    {
        parent::setUp();

        $this->userA = factory(User::class)->create([
            'name' => "Oliver Nybroe"
        ]);
        $this->userB = factory(User::class)->create([
            'name' => "Christopher Moore"
        ]);

        $this->postA = factory(Post::class)->create([
            'title' => 'great title',
            'user_id' => $this->userA->id
        ]);
        $this->postB = factory(Post::class)->create([
            'title' => 'Really great title',
            'user_id' => $this->userA->id
        ]);
        $this->postC = factory(Post::class)->create([
            'title' => 'bad title is the worse'
        ]);
        $this->postD = factory(Post::class)->create([
            'title' => 'admin title',
            'user_id' => $this->userB->id
        ]);
        $this->postE = factory(Post::class)->create([
            'title' => 'Yet another one'
        ]);

        $this->schema = '    
        type Post @model @filter {
            id: ID!
            title: String!
            author: User! @belongsTo(relation: "user")
        }
        
        type User @model @filter {
            id: ID!
            name: String!
        } 
  
        type Query {
            posts(filter: PostFilter @filter): [Post!]! @paginate(type: "paginator" model: "Post")
        }
        ';
    }

    protected function runGraphQl($query)
    {
        return parent::execute($this->schema, $query);
    }

    /** @test */
    public function can_filter_string_equals()
    {
        $query = "{
            posts(count: 10, filter: {title: \"{$this->postB->title}\"}) {
                data {
                    title
                }
            }
        }";

        $results = $this->runGraphQl($query)->data;
        $results = Arr::get($results, 'posts.data');

        $this->assertCount(1, $results);
        $this->assertEquals($this->postB->title, Arr::get($results, '0.title'));
    }

    /** @test */
    public function can_filter_string_starts_with()
    {
        $query = '{
            posts(count: 10, filter: {title_starts_with: "great"}) {
                data {
                    title
                }
            }
        }';

        dd("query ran", $this->runGraphQl($query)->toArray());
        $results = $this->runGraphQl($query)->data;
        $results = Arr::get($results, 'posts.data');

        $this->assertCount(1, $results);
        $this->assertEquals($this->postA->title, Arr::get($results, '0.title'));
    }

    /** @test */
    public function can_filter_string_not_starts_with()
    {
        $query = '{
            posts(count: 10, filter: {title_not_starts_with: "great"}) {
                data {
                    title
                }
            }
        }';

        $results = $this->runGraphQl($query)->data;
        $results = Arr::get($results, 'posts.data');

        $this->assertCount(4, $results);
        $this->assertEquals($this->postB->title, Arr::get($results, '0.title'));
        $this->assertEquals($this->postC->title, Arr::get($results, '1.title'));
        $this->assertEquals($this->postD->title, Arr::get($results, '2.title'));
        $this->assertEquals($this->postE->title, Arr::get($results, '3.title'));
    }

    /** @test */
    public function can_filter_string_ends_with()
    {
        $query = '{
            posts(count: 10, filter: {title_ends_with: "title"}) {
                data {
                    title
                }
            }
        }';

        $results = $this->runGraphQl($query)->data;
        $results = Arr::get($results, 'posts.data');

        $this->assertCount(3, $results);
        $this->assertEquals($this->postA->title, Arr::get($results, '0.title'));
        $this->assertEquals($this->postB->title, Arr::get($results, '1.title'));
        $this->assertEquals($this->postD->title, Arr::get($results, '2.title'));
    }

    /** @test */
    public function can_filter_string_not_ends_with()
    {
        $query = '{
            posts(count: 10, filter: {title_not_ends_with: "title"}) {
                data {
                    title
                }
            }
        }';

        $results = $this->runGraphQl($query)->data;
        $results = Arr::get($results, 'posts.data');

        $this->assertCount(2, $results);
        $this->assertEquals($this->postC->title, Arr::get($results, '0.title'));
        $this->assertEquals($this->postE->title, Arr::get($results, '1.title'));
    }

    /** @test */
    public function can_filter_string_in()
    {
        $query = '{
            posts(count: 10, filter: {title_in: ["great title", "bad title is the worse"]}) {
                data {
                    title
                }
            }
        }';

        $results = $this->runGraphQl($query)->data;
        $results = Arr::get($results, 'posts.data');

        $this->assertCount(2, $results);
        $this->assertEquals($this->postA->title, Arr::get($results, '0.title'));
        $this->assertEquals($this->postC->title, Arr::get($results, '1.title'));
    }

    /** @test */
    public function can_filter_string_not_in()
    {
        $query = '{
            posts(count: 10, filter: {title_not_in: ["great title", "bad title is the worse"]}) {
                data {
                    title
                }
            }
        }';

        $results = $this->runGraphQl($query)->data;
        $results = Arr::get($results, 'posts.data');

        $this->assertCount(3, $results);
        $this->assertEquals($this->postB->title, Arr::get($results, '0.title'));
        $this->assertEquals($this->postD->title, Arr::get($results, '1.title'));
        $this->assertEquals($this->postE->title, Arr::get($results, '2.title'));
    }

    /** @test */
    public function can_filter_string_not_equals()
    {
        $query = '{
            posts(count: 10, filter: {title_not: "Really great title"}) {
                data {
                    title
                }
            }
        }';

        $results = $this->runGraphQl($query)->data;
        $results = Arr::get($results, 'posts.data');

        $this->assertCount(4, $results);
        $this->assertEquals($this->postA->title, Arr::get($results, '0.title'));
        $this->assertEquals($this->postC->title, Arr::get($results, '1.title'));
        $this->assertEquals($this->postD->title, Arr::get($results, '2.title'));
        $this->assertEquals($this->postE->title, Arr::get($results, '3.title'));
    }

    /** @test */
    public function can_filter_string_contains()
    {
        $query = '{
            posts(count: 10, filter: {title_contains: "great"}) {
                data {
                    title
                }
            }
        }';

        $results = $this->runGraphQl($query)->data;
        $results = Arr::get($results, 'posts.data');

        $this->assertCount(2, $results);
        $this->assertEquals($this->postA->title, Arr::get($results, '0.title'));
        $this->assertEquals($this->postB->title, Arr::get($results, '1.title'));
    }

    /** @test */
    public function can_filter_string_not_contains()
    {
        $query = '{
            posts(count: 10, filter: {title_not_contains: "great"}) {
                data {
                    title
                }
            }
        }';

        $results = $this->runGraphQl($query)->data;
        $results = Arr::get($results, 'posts.data');

        $this->assertCount(3, $results);
        $this->assertEquals($this->postC->title, Arr::get($results, '0.title'));
        $this->assertEquals($this->postD->title, Arr::get($results, '1.title'));
        $this->assertEquals($this->postE->title, Arr::get($results, '2.title'));
    }

    /** @test */
    public function can_filter_with_and()
    {
        $query = '{
            posts(count: 10, filter: {AND: [{title_contains: "title"}, {title_contains: "great"}]}) {
                data {
                    title
                }
            }
        }';

        $results = $this->runGraphQl($query)->data;
        $results = Arr::get($results, 'posts.data');

        $this->assertCount(2, $results);
        $this->assertEquals($this->postA->title, Arr::get($results, '0.title'));
        $this->assertEquals($this->postB->title, Arr::get($results, '1.title'));
    }

    /** @test */
    public function can_filter_with_or()
    {
        $query = '{
            posts(count: 10, filter: {OR: [{title_contains: "bad"}, {title_contains: "Really"}]}) {
                data {
                    title
                }
            }
        }';

        $results = $this->runGraphQl($query)->data;
        $results = Arr::get($results, 'posts.data');

        $this->assertCount(2, $results);
        $this->assertEquals($this->postB->title, Arr::get($results, '0.title'));
        $this->assertEquals($this->postC->title, Arr::get($results, '1.title'));
    }

    /** @test */
    public function can_filter_with_nested_or()
    {
        $query = '{
            posts(count: 10, filter: {
                    AND: [
                        {
                            OR: [
                                {
                                    title_contains: "admin"
                                }, 
                                {
                                    title_contains: "great"
                                }
                            ]
                        },
                        {
                            title_contains: "title"
                        }
                    ]
                }) {
                data {
                    title
                }
            }
        }';

        $results = $this->runGraphQl($query)->data;
        $results = Arr::get($results, 'posts.data');

        $this->assertCount(3, $results);
        $this->assertEquals($this->postA->title, Arr::get($results, '0.title'));
        $this->assertEquals($this->postB->title, Arr::get($results, '1.title'));
        $this->assertEquals($this->postD->title, Arr::get($results, '2.title'));
    }

    /** @test */
    public function can_filter_with_nested_and()
    {
        $query = '{
            posts(count: 10, filter: {
                    OR: [
                        {
                            AND: [
                                {
                                    title_contains: "title"
                                }, 
                                {
                                    title_contains: "great"
                                }
                            ]
                        },
                        {
                            title_contains: "admin"
                        }
                    ]
                }) {
                data {
                    title
                }
            }
        }';

        $results = $this->runGraphQl($query)->data;
        $results = Arr::get($results, 'posts.data');

        $this->assertCount(3, $results);
        $this->assertEquals($this->postA->title, Arr::get($results, '0.title'));
        $this->assertEquals($this->postB->title, Arr::get($results, '1.title'));
        $this->assertEquals($this->postD->title, Arr::get($results, '2.title'));
    }

    /** @test */
    public function can_filter_from_relation_filter()
    {
        $query = '{
            posts(count: 10, filter: {
                    author: {
                        name: "Oliver Nybroe"
                    }
                }) {
                data {
                    author {
                        name
                    }
                }
            }
        }';

        $results = $this->runGraphQl($query);
        dd($results->toArray($debug = Debug::INCLUDE_DEBUG_MESSAGE | Debug::INCLUDE_TRACE));
        $results = Arr::get($results, 'posts.data');

        $this->assertCount(2, $results);
        $this->assertEquals($this->userA->name, Arr::get($results, '0.author.name'));
        $this->assertEquals($this->userA->name, Arr::get($results, '1.author.name'));
    }

    /** @test */
    public function can_filter_from_relation_filter_nested()
    {

    }

    /** @test */
    public function can_filter_from_to_many_relation_filter()
    {

    }

    /** @test */
    public function can_filter_from_to_many_relation_filter_nested()
    {

    }

    /** @test */
    public function can_filter_from_to_many_relation_filter_using_every()
    {

    }

    /** @test */
    public function can_filter_from_to_many_relation_filter_using_some()
    {

    }

    /** @test */
    public function can_filter_from_to_many_relation_filter_using_none()
    {

    }
}