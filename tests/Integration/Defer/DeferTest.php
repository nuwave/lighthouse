<?php

namespace Tests\Integration\Defer;

use Tests\TestCase;
use GraphQL\Error\Error;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Defer\Defer;
use Nuwave\Lighthouse\Defer\DeferServiceProvider;

class DeferTest extends TestCase
{
    use SetUpDefer;

    /**
     * @var mixed[]
     */
    public static $data = [];

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $this->setUpDefer($app);
    }

    protected function getPackageProviders($app)
    {
        return array_merge(
            parent::getPackageProviders($app),
            [DeferServiceProvider::class]
        );
    }

    /**
     * @test
     */
    public function itCanDeferFields(): void
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
        ";

        $chunks = $this->getStreamedChunks('
        { 
            user {
                name
                parent @defer {
                    name
                }
            }
        }
        ');

        $this->assertSame(
            [
                [
                    'data' => [
                        'user' => [
                            'name' => 'John Doe',
                            'parent' => null,
                        ],
                    ],
                ],
                [
                    'user.parent' => [
                        'data' => [
                            'name' => 'Jane Doe',
                        ],
                    ],
                ],
            ],
            $chunks
        );
    }

    /**
     * @test
     */
    public function itCanDeferNestedFields(): void
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
        }
        ";

        $chunks = $this->getStreamedChunks('
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
        }
        ');

        $this->assertCount(3, $chunks);

        $this->assertSame(self::$data['name'], Arr::get($chunks[0], 'data.user.name'));
        $this->assertNull(Arr::get($chunks[0], 'data.user.parent'));

        $deferred = Arr::get($chunks[1], 'user.parent');
        $this->assertArrayHasKey('name', $deferred['data']);
        $this->assertSame(self::$data['parent']['name'], $deferred['data']['name']);
        $this->assertArrayHasKey('parent', $deferred['data']);
        $this->assertNull($deferred['data']['parent']);

        $nestedDeferred = Arr::get($chunks[2], 'user.parent.parent');
        $this->assertArrayHasKey('name', $nestedDeferred['data']);
        $this->assertSame(self::$data['parent']['parent']['name'], $nestedDeferred['data']['name']);
    }

    /**
     * @test
     */
    public function itCanDeferListFields(): void
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
        }
        ";

        $chunks = $this->getStreamedChunks('
        { 
            posts {
                title
                author @defer {
                    name
                }
            }
        }
        ');

        $this->assertCount(2, $chunks);

        $this->assertNull(Arr::get($chunks[0], 'data.posts.0.author'));
        $this->assertNull(Arr::get($chunks[0], 'data.posts.1.author'));

        $deferredPost1 = $chunks[1]['posts.0.author']['data'];
        $this->assertSame(self::$data[0]['author']['name'], Arr::get($deferredPost1, 'name'));

        $deferredPost2 = $chunks[1]['posts.1.author']['data'];
        $this->assertSame(self::$data[1]['author']['name'], Arr::get($deferredPost2, 'name'));
    }

    /**
     * @test
     */
    public function itCanDeferGroupedListFields(): void
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
        }
        ";

        $chunks = $this->getStreamedChunks('
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
        }
        ');

        $this->assertCount(2, $chunks);

        $this->assertNull(Arr::get($chunks[0], 'data.posts.0.author'));
        $this->assertNull(Arr::get($chunks[0], 'data.posts.1.author'));

        $deferredPost1 = $chunks[1]['posts.0.author']['data'];
        $this->assertSame(self::$data[0]['author']['name'], Arr::get($deferredPost1, 'name'));

        $deferredComment1 = $chunks[1]['posts.0.comments']['data'];
        $this->assertCount(1, $deferredComment1);
        $this->assertSame(self::$data[0]['comments'][0]['message'], Arr::get($deferredComment1[0], 'message'));

        $deferredPost2 = $chunks[1]['posts.1.author']['data'];
        $this->assertSame(self::$data[1]['author']['name'], Arr::get($deferredPost2, 'name'));

        $deferredComment2 = $chunks[1]['posts.1.comments']['data'];
        $this->assertCount(1, $deferredComment2);
        $this->assertSame(self::$data[1]['comments'][0]['message'], Arr::get($deferredComment2[0], 'message'));
    }

    /**
     * @test
     */
    public function itCancelsDefermentAfterMaxExecutionTime(): void
    {
        $resolver = addslashes(self::class).'@resolve';
        $this->schema = "
        type User {
            name: String!
            parent: User
        }

        type Query {
            user: User @field(resolver: \"{$resolver}\")
        }
        ";

        /** @var \Nuwave\Lighthouse\Defer\Defer $defer */
        $defer = app(Defer::class);
        // Set max execution time to now so we immediately resolve deferred fields
        $defer->setMaxExecutionTime(microtime(true));

        self::$data = [
            'name' => 'John Doe',
            'parent' => [
                'name' => 'Jane Doe',
                'parent' => [
                    'name' => 'Mr. Smith',
                ],
            ],
        ];

        $chunks = $this->getStreamedChunks('
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
        }
        ');

        // If we didn't hit the max execution time we would have 3 items in the array
        $this->assertCount(2, $chunks);

        $this->assertSame(self::$data['name'], Arr::get($chunks[0], 'data.user.name'));
        $this->assertNull(Arr::get($chunks[0], 'data.user.parent'));

        $deferred = Arr::get($chunks[1], 'user.parent');
        $this->assertArrayHasKey('name', $deferred['data']);
        $this->assertSame(self::$data['parent']['name'], $deferred['data']['name']);
        $this->assertArrayHasKey('parent', $deferred['data']);
        $this->assertSame(self::$data['parent']['parent']['name'], $deferred['data']['parent']['name']);
    }

    /**
     * @test
     */
    public function itCancelsDefermentAfterMaxNestedFields(): void
    {
        $resolver = addslashes(self::class).'@resolve';
        $this->schema = "
        type User {
            name: String!
            parent: User
        }

        type Query {
            user: User @field(resolver: \"{$resolver}\")
        }
        ";

        /** @var \Nuwave\Lighthouse\Defer\Defer $defer */
        $defer = app(Defer::class);
        $defer->setMaxNestedFields(1);

        self::$data = [
            'name' => 'John Doe',
            'parent' => [
                'name' => 'Jane Doe',
                'parent' => [
                    'name' => 'Mr. Smith',
                ],
            ],
        ];

        $chunks = $this->getStreamedChunks('
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
        }
        ');

        $this->assertCount(2, $chunks);

        $this->assertSame(self::$data['name'], Arr::get($chunks[0], 'data.user.name'));
        $this->assertNull(Arr::get($chunks[0], 'data.user.parent'));

        $deferred = Arr::get($chunks[1], 'user.parent');
        $this->assertArrayHasKey('name', $deferred['data']);
        $this->assertSame(self::$data['parent']['name'], $deferred['data']['name']);
        $this->assertArrayHasKey('parent', $deferred['data']);
        $this->assertSame(self::$data['parent']['parent']['name'], $deferred['data']['parent']['name']);
    }

    /**
     * @test
     */
    public function itThrowsExceptionOnNunNullableFields(): void
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
        }
        ";

        $this->query('
        { 
            user {
                name
                parent @defer {
                    name
                }
            }
        }
        ')->assertJson([
            'errors' => [
                [
                    'message' => 'The @defer directive cannot be placed on a Non-Nullable field.',
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function itSkipsDeferWithIncludeAndSkipDirectives(): void
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
        }
        ";

        $this->query('
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
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'name' => 'John Doe',
                    'parent' => [
                        'name' => 'Jane Doe',
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function itRequiresDeferDirectiveOnAllFieldDeclarations(): void
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
        ";

        $this->query('
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
        }
        ')->assertJson([
            'data' => [
                'user' => self::$data,
            ],
        ]);
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
    public function itSkipsDeferredFieldsOnMutations(): void
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
        }
        ";

        $this->query('
        mutation UpdateUser {
            updateUser(name: "John Doe") {
                name 
                parent @defer {
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'updateUser' => self::$data,
            ],
        ]);
    }

    /**
     * @test
     */
    public function itDoesNotDeferFieldsIfFalse(): void
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
        ";

        $this->query('
        {
            user {
                name
                parent @defer(if: false) {
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'user' => self::$data,
            ],
        ]);
    }

    /**
     * @test
     */
    public function itIncludesErrorsForDeferredFields(): void
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
        }
        ";

        $chunks = $this->getStreamedChunks('
        {
            user {
                name
                parent @defer {
                    name
                }
            }
        }
        ');

        $this->assertCount(2, $chunks);

        $parent = $chunks[1];
        $this->assertArrayHasKey('user.parent', $parent);
        $this->assertNull($parent['user.parent']['data']);
        $this->assertArrayHasKey('errors', $parent['user.parent']);
        $this->assertCount(1, $parent['user.parent']['errors']);
    }

    public function resolve(): array
    {
        return self::$data;
    }

    /**
     * @throws \GraphQL\Error\Error
     */
    public function throw(): void
    {
        throw new Error('deferred_exception');
    }
}
