<?php

namespace Tests\Unit\Schema\Extensions;

use Tests\TestCase;
use Nuwave\Lighthouse\Schema\Extensions\DeferExtension;
use Nuwave\Lighthouse\Support\Contracts\CanStreamResponse;
use Nuwave\Lighthouse\Support\Http\Responses\MemoryStream;

class DeferExtensionTest extends TestCase
{
    /** @var array */
    public static $data = [];

    /** @var MemoryStream */
    protected $stream;

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $this->stream = new MemoryStream();

        $app->singleton(CanStreamResponse::class, function () {
            return $this->stream;
        });

        $app['config']->set('lighthouse.extensions', [DeferExtension::class]);
        $app['config']->set('app.debug', true);
    }

    /**
     * @test
     */
    public function itCanDeferFields()
    {
        self::$data = [
            'name' => 'John Doe',
            'parent' => [
                'name' => 'Jane Doe',
            ],
        ];

        $resolver = addslashes(self::class).'@resolve';
        $this->schema = "
        type User {
            name: String!
            parent: User
        }

        type Query {
            user: User @field(resolver: \"{$resolver}\")
        }";

        $query = '
        { 
            user {
                name
                parent @defer {
                    name
                }
            }
        }';

        $this->postJson('/graphql', compact('query'))
            ->baseResponse
            ->send();

        $chunks = $this->stream->chunks;

        $this->assertCount(2, $chunks);
        $this->assertEquals('John Doe', array_get($chunks[0], 'data.user.name'));
        $this->assertNull(array_get($chunks[0], 'data.user.parent'));
        $deferred = array_get($chunks[1], 'user.parent');
        $this->assertArrayHasKey('name', $deferred);
        $this->assertEquals('Jane Doe', $deferred['name']);
    }

    /**
     * @test
     */
    public function itCanDeferNestedFields()
    {
        self::$data = [
            'name' => 'John Doe',
            'parent' => [
                'name' => 'Jane Doe',
                'parent' => [
                    'name' => 'Mr. Smith',
                ],
            ],
        ];

        $resolver = addslashes(self::class).'@resolve';
        $this->schema = "
        type User {
            name: String!
            parent: User
        }

        type Query {
            user: User @field(resolver: \"{$resolver}\")
        }";

        $query = '
        { 
            user {
                name
                parent @defer {
                    name
                    parent @defer {
                        name
                    }
                }
            }
        }';

        $this->postJson('/graphql', compact('query'))
            ->baseResponse
            ->send();

        $chunks = $this->stream->chunks;

        $this->assertCount(3, $chunks);
        $this->assertEquals(self::$data['name'], array_get($chunks[0], 'data.user.name'));
        $this->assertNull(array_get($chunks[0], 'data.user.parent'));

        $deferred = array_get($chunks[1], 'user.parent');
        $this->assertArrayHasKey('name', $deferred);
        $this->assertEquals(self::$data['parent']['name'], $deferred['name']);
        $this->assertArrayHasKey('parent', $deferred);
        $this->assertNull($deferred['parent']);

        $nestedDeferred = array_get($chunks[2], 'user.parent.parent');
        $this->assertArrayHasKey('name', $nestedDeferred);
        $this->assertEquals(self::$data['parent']['parent']['name'], $nestedDeferred['name']);
    }

    /**
     * @test
     */
    public function itCanDeferListFields()
    {
        self::$data = [
            [
                'title' => 'Foo',
                'author' => [
                    'name' => 'John Doe',
                ],
            ],
            [
                'title' => 'Bar',
                'author' => [
                    'name' => 'Jane Doe',
                ],
            ],
        ];

        $resolver = addslashes(self::class).'@resolve';
        $this->schema = "
        type Post {
            title: String
            author: User
        }
        type User {
            name: String!
        }
        type Query {
            posts: [Post] @field(resolver: \"{$resolver}\")
        }";

        $query = '
        { 
            posts {
                title
                author @defer {
                    name
                }
            }
        }';

        $this->postJson('/graphql', compact('query'))
            ->baseResponse
            ->send();

        $chunks = $this->stream->chunks;
        $this->assertCount(2, $chunks);
        $this->assertNull(array_get($chunks[0], 'data.posts.0.author'));
        $this->assertNull(array_get($chunks[0], 'data.posts.1.author'));

        $deferredPost1 = $chunks[1]['posts.0.author'];
        $this->assertEquals(self::$data[0]['author']['name'], array_get($deferredPost1, 'name'));

        $deferredPost2 = $chunks[1]['posts.1.author'];
        $this->assertEquals(self::$data[1]['author']['name'], array_get($deferredPost2, 'name'));
    }

    /**
     * @test
     */
    public function itCanDeferGroupedListFields()
    {
        self::$data = [
            [
                'title' => 'Foo',
                'author' => [
                    'name' => 'John Doe',
                ],
                'comments' => [
                    ['message' => 'foobar'],
                ],
            ],
            [
                'title' => 'Bar',
                'author' => [
                    'name' => 'Jane Doe',
                ],
                'comments' => [
                    ['message' => 'foobar'],
                ],
            ],
        ];

        $resolver = addslashes(self::class).'@resolve';
        $this->schema = "
        type Comment {
            message: String
        }
        type Post {
            title: String
            author: User
            comments: [Comment]
        }
        type User {
            name: String!
        }
        type Query {
            posts: [Post] @field(resolver: \"{$resolver}\")
        }";

        $query = '
        { 
            posts {
                title
                author @defer {
                    name
                }
                comments @defer {
                    message
                }
            }
        }';

        $this->postJson('/graphql', compact('query'))
            ->baseResponse
            ->send();

        $chunks = $this->stream->chunks;
        $this->assertCount(2, $chunks);
        $this->assertNull(array_get($chunks[0], 'data.posts.0.author'));
        $this->assertNull(array_get($chunks[0], 'data.posts.1.author'));

        $deferredPost1 = $chunks[1]['posts.0.author'];
        $this->assertEquals(self::$data[0]['author']['name'], array_get($deferredPost1, 'name'));

        $deferredComment1 = $chunks[1]['posts.0.comments'];
        $this->assertCount(1, $deferredComment1);
        $this->assertEquals(self::$data[0]['comments'][0]['message'], array_get($deferredComment1[0], 'message'));

        $deferredPost2 = $chunks[1]['posts.1.author'];
        $this->assertEquals(self::$data[1]['author']['name'], array_get($deferredPost2, 'name'));

        $deferredComment2 = $chunks[1]['posts.1.comments'];
        $this->assertCount(1, $deferredComment2);
        $this->assertEquals(self::$data[1]['comments'][0]['message'], array_get($deferredComment2[0], 'message'));
    }

    public function resolve()
    {
        return self::$data;
    }
}
