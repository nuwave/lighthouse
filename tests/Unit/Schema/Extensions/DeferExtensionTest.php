<?php

namespace Tests\Unit\Schema\Extensions;

use Tests\TestCase;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Schema\Extensions\DeferExtension;
use Nuwave\Lighthouse\Schema\Extensions\ExtensionRegistry;
use Nuwave\Lighthouse\Support\Contracts\CanStreamResponse;
use Nuwave\Lighthouse\Support\Http\Responses\MemoryStream;

class DeferExtensionTest extends TestCase
{
    /** @var MemoryStream */
    protected $stream;

    /** @var array */
    public static $data = [];

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
        $this->assertSame('John Doe', Arr::get($chunks[0], 'data.user.name'));
        $this->assertNull(Arr::get($chunks[0], 'data.user.parent'));
        $deferred = Arr::get($chunks[1], 'user.parent');
        $this->assertArrayHasKey('name', $deferred['data']);
        $this->assertSame('Jane Doe', $deferred['data']['name']);
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
        $this->assertEquals(self::$data['name'], Arr::get($chunks[0], 'data.user.name'));
        $this->assertNull(Arr::get($chunks[0], 'data.user.parent'));

        $deferred = Arr::get($chunks[1], 'user.parent');
        $this->assertArrayHasKey('name', $deferred['data']);
        $this->assertEquals(self::$data['parent']['name'], $deferred['data']['name']);
        $this->assertArrayHasKey('parent', $deferred['data']);
        $this->assertNull($deferred['data']['parent']);

        $nestedDeferred = Arr::get($chunks[2], 'user.parent.parent');
        $this->assertArrayHasKey('name', $nestedDeferred['data']);
        $this->assertEquals(self::$data['parent']['parent']['name'], $nestedDeferred['data']['name']);
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
        $this->assertNull(Arr::get($chunks[0], 'data.posts.0.author'));
        $this->assertNull(Arr::get($chunks[0], 'data.posts.1.author'));

        $deferredPost1 = $chunks[1]['posts.0.author']['data'];
        $this->assertEquals(self::$data[0]['author']['name'], Arr::get($deferredPost1, 'name'));

        $deferredPost2 = $chunks[1]['posts.1.author']['data'];
        $this->assertEquals(self::$data[1]['author']['name'], Arr::get($deferredPost2, 'name'));
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
        $this->assertNull(Arr::get($chunks[0], 'data.posts.0.author'));
        $this->assertNull(Arr::get($chunks[0], 'data.posts.1.author'));

        $deferredPost1 = $chunks[1]['posts.0.author']['data'];
        $this->assertEquals(self::$data[0]['author']['name'], Arr::get($deferredPost1, 'name'));

        $deferredComment1 = $chunks[1]['posts.0.comments']['data'];
        $this->assertCount(1, $deferredComment1);
        $this->assertEquals(self::$data[0]['comments'][0]['message'], Arr::get($deferredComment1[0], 'message'));

        $deferredPost2 = $chunks[1]['posts.1.author']['data'];
        $this->assertEquals(self::$data[1]['author']['name'], Arr::get($deferredPost2, 'name'));

        $deferredComment2 = $chunks[1]['posts.1.comments']['data'];
        $this->assertCount(1, $deferredComment2);
        $this->assertEquals(self::$data[1]['comments'][0]['message'], Arr::get($deferredComment2[0], 'message'));
    }

    /**
     * @test
     */
    public function itCancelsDefermentAfterMaxExecutionTime()
    {
        /** @var DeferExtension $deferExtension */
        $deferExtension = app(ExtensionRegistry::class)->get(DeferExtension::name());
        // Set max execution time to now so we immediately resolve deferred fields
        $deferExtension->setMaxExecutionTime(microtime(true));

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
        // If we didn't hit the max execution time we would have 3 items in the array
        $this->assertCount(2, $chunks);

        $this->assertEquals(self::$data['name'], Arr::get($chunks[0], 'data.user.name'));
        $this->assertNull(Arr::get($chunks[0], 'data.user.parent'));

        $deferred = Arr::get($chunks[1], 'user.parent');
        $this->assertArrayHasKey('name', $deferred['data']);
        $this->assertEquals(self::$data['parent']['name'], $deferred['data']['name']);
        $this->assertArrayHasKey('parent', $deferred['data']);
        $this->assertEquals(self::$data['parent']['parent']['name'], $deferred['data']['parent']['name']);
    }

    /**
     * @test
     */
    public function itCancelsDefermentAfterMaxNestedFields()
    {
        /** @var DeferExtension $deferExtension */
        $deferExtension = app(ExtensionRegistry::class)->get(DeferExtension::name());
        $deferExtension->setMaxNestedFields(1);

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
        $this->assertCount(2, $chunks);

        $this->assertEquals(self::$data['name'], Arr::get($chunks[0], 'data.user.name'));
        $this->assertNull(Arr::get($chunks[0], 'data.user.parent'));

        $deferred = Arr::get($chunks[1], 'user.parent');
        $this->assertArrayHasKey('name', $deferred['data']);
        $this->assertEquals(self::$data['parent']['name'], $deferred['data']['name']);
        $this->assertArrayHasKey('parent', $deferred['data']);
        $this->assertEquals(self::$data['parent']['parent']['name'], $deferred['data']['parent']['name']);
    }

    /**
     * @test
     */
    public function itThrowsExceptionOnNunNullableFields()
    {
        config([
            'lighthouse.defer.max_nested_fields' => 1,
            'app.debug' => false,
        ]);

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
            parent: User!
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

        $response = $this->postJson('/graphql', compact('query'))->json();

        $this->assertArrayHasKey('errors', $response);
        $this->assertArrayHaskey('category', $response['errors'][0]['extensions']);
    }

    /**
     * @test
     */
    public function itSkipsDeferWithIncludeAndSkipDirectives()
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
        directive @include(if: Boolean!) on FIELD
        directive @skip(if: Boolean!) on FIELD

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
                parent @defer @include(if: true) {
                    name
                    parent @defer @skip(if: true) {
                        name
                    }
                }
            }
        }';

        $response = $this->postJson('/graphql', compact('query'))->json();

        $this->assertEquals(
            [
                'name' => 'John Doe',
                'parent' => ['name' => 'Jane Doe'],
            ],
            Arr::get($response, 'data.user')
        );
    }

    /**
     * @test
     */
    public function itRequiresDeferDirectiveOnAllFieldDeclarations()
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
        fragment UserWithParent on User {
            name
            parent {
                name
            }
        }
        { 
            user {
                ...UserWithParent
                parent @defer {
                    name
                }
            }
        }';

        $response = $this->postJson('/graphql', compact('query'))->json();

        $this->assertEquals(self::$data, Arr::get($response, 'data.user'));
    }

    /**
     * @test
     *
     * @todo Ensure that this functions the same way as Apollo Server.
     * Currently in the documentation it just says "Not Supported" instead
     * of specifying if it throws an error or not.
     *
     * https://www.apollographql.com/docs/react/features/defer-support.html#defer-usage
     */
    public function itSkipsDeferredFieldsOnMutations()
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
        }
        type Mutation {
            updateUser(name: String!): User
                @field(resolver: \"{$resolver}\")
        }";

        $query = '
        mutation UpdateUser {
            updateUser(name: "John Doe") {
                name 
                parent @defer {
                    name
                }
            }
        }';

        $response = $this->postJson('/graphql', compact('query'))->json();
        $this->assertEquals(self::$data, Arr::get($response, 'data.updateUser'));
    }

    /**
     * @test
     */
    public function itDoesNotDeferFieldsIfFalse()
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
                parent @defer(if: false) {
                    name
                }
            }
        }';

        $response = $this->postJson('/graphql', compact('query'))->json();
        $this->assertEquals(self::$data, Arr::get($response, 'data.user'));
    }

    /**
     * @test
     */
    public function itIncludesErrorsForDeferredFields()
    {
        self::$data = [
            'name' => 'John Doe',
            'parent' => [
                'name' => 'Jane Doe',
            ],
        ];

        $resolver = addslashes(self::class).'@resolve';
        $throw = addslashes(self::class).'@throw';
        $this->schema = "
        type User {
            name: String!
            parent: User @field(resolver: \"{$throw}\")
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

        $this->postJson('/graphql', compact('query'))->baseResponse->send();

        $chunks = $chunks = $this->stream->chunks;
        $this->assertCount(2, $chunks);

        $parent = $chunks[1];
        $this->assertArrayHasKey('user.parent', $parent);
        $this->assertNull($parent['user.parent']['data']);
        $this->assertArrayHasKey('errors', $parent['user.parent']);
        $this->assertCount(1, $parent['user.parent']['errors']);
    }

    public function resolve()
    {
        return self::$data;
    }

    public function throw()
    {
        throw new \Exception('deferred_exception');
    }
}
