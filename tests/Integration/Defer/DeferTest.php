<?php

namespace Tests\Integration\Defer;

use Tests\TestCase;
use GraphQL\Error\Error;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Defer\Defer;
use Nuwave\Lighthouse\Defer\DeferrableDirective;
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
    public function itAddsTheDeferClientDirective(): void
    {
        $this->schema = $this->placeholderQuery();

        $introspection = $this->graphQL('
          query IntrospectionQuery {
            __schema {
              directives {
                name
              }
            }
          }
        ');

        $this->assertTrue(
            in_array(
                'defer',
                $introspection->jsonGet('data.__schema.directives.*.name')
            )
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

        $this->schema = "
        type User {
            name: String!
            parent: User
        }

        type Query {
            user: User @field(resolver: \"{$this->qualifyTestResolver()}\")
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

        $this->schema = "
        type User {
            name: String!
            parent: User
        }

        type Query {
            user: User @field(resolver: \"{$this->qualifyTestResolver()}\")
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
    public function itCanDeferNestedFieldsOnMutations(): void
    {
        self::$data = [
            'name' => 'John Doe',
            'parent' => [
                'name' => 'Jane Doe',
            ],
        ];

        $this->schema = "
        type User {
            name: String!
            parent: User
        }
        
        type Query {
            user: User @field(resolver: \"{$this->qualifyTestResolver()}\")
        }
        
        type Mutation {
            updateUser(name: String!): User
                @field(resolver: \"{$this->qualifyTestResolver()}\")
        }
        ";

        $chunks = $this->getStreamedChunks('
        mutation { 
            updateUser(
                name: "Foo"
            ) {
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
                        'updateUser' => [
                            'name' => 'John Doe',
                            'parent' => null,
                        ],
                    ],
                ],
                [
                    'updateUser.parent' => [
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

        $this->schema = "
        type Post {
            title: String
            author: User
        }
        
        type User {
            name: String!
        }
        
        type Query {
            posts: [Post] @field(resolver: \"{$this->qualifyTestResolver()}\")
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
            posts: [Post] @field(resolver: \"{$this->qualifyTestResolver()}\")
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
        $this->schema = "
        type User {
            name: String!
            parent: User
        }

        type Query {
            user: User @field(resolver: \"{$this->qualifyTestResolver()}\")
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
        $this->schema = "
        type User {
            name: String!
            parent: User
        }

        type Query {
            user: User @field(resolver: \"{$this->qualifyTestResolver()}\")
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
        self::$data = [
            'name' => 'John Doe',
            'parent' => [
                'name' => 'Jane Doe',
            ],
        ];

        $this->schema = "
        type User {
            name: String!
            parent: User!
        }

        type Query {
            user: User @field(resolver: \"{$this->qualifyTestResolver()}\")
        }
        ";

        $this->graphQL('
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
                    'message' => DeferrableDirective::THE_DEFER_DIRECTIVE_CANNOT_BE_USED_ON_A_NON_NULLABLE_FIELD,
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function itDoesNotDeferWithIncludeAndSkipDirectives(): void
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

        $this->schema = "
        directive @include(if: Boolean!) on FIELD
        directive @skip(if: Boolean!) on FIELD

        type User {
            name: String!
            parent: User
        }

        type Query {
            user: User @field(resolver: \"{$this->qualifyTestResolver()}\")
        }
        ";

        $this->graphQL('
        { 
            userInclude: user {
                name
                parent @defer @include(if: false) {
                    name
                }
            }
            userSkip: user {
                name
                parent @defer @skip(if: true) {
                    name
                }
            }
        }
        ')->assertExactJson([
            'data' => [
                'userInclude' => [
                    'name' => 'John Doe',
                ],
                'userSkip' => [
                    'name' => 'John Doe',
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

        $this->schema = "
        type User {
            name: String!
            parent: User
        }

        type Query {
            user: User @field(resolver: \"{$this->qualifyTestResolver()}\")
        }
        ";

        $this->graphQL('
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
     */
    public function itThrowsIfTryingToDeferRootMutationFields(): void
    {
        self::$data = [
            'name' => 'John Doe',
        ];

        $this->schema = "
        type User {
            name: String!
            parent: User
        }
        
        type Query {
            user: User @field(resolver: \"{$this->qualifyTestResolver()}\")
        }
        
        type Mutation {
            updateUser(name: String!): User
                @field(resolver: \"{$this->qualifyTestResolver()}\")
        }
        ";

        $this->graphQL('
        mutation UpdateUser {
            updateUser(name: "John Doe") @defer {
                name 
            }
        }
        ')->assertJson([
            'errors' => [
                [
                    'message' => DeferrableDirective::THE_DEFER_DIRECTIVE_CANNOT_BE_USED_ON_A_ROOT_MUTATION_FIELD,
                ],
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

        $this->schema = "
        type User {
            name: String!
            parent: User
        }
        
        type Query {
            user: User @field(resolver: \"{$this->qualifyTestResolver()}\")
        }
        ";

        $this->graphQL('
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

        $this->schema = "
        type User {
            name: String!
            parent: User @field(resolver: \"{$this->qualifyTestResolver('throw')}\")
        }
        
        type Query {
            user: User @field(resolver: \"{$this->qualifyTestResolver()}\")
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
