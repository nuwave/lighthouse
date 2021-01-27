<?php

namespace Tests\Integration\Defer;

use GraphQL\Error\Error;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Defer\Defer;
use Nuwave\Lighthouse\Defer\DeferrableDirective;
use Nuwave\Lighthouse\Defer\DeferServiceProvider;
use Tests\TestCase;

class DeferTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->setUpDeferStream();
    }

    protected function getPackageProviders($app): array
    {
        return array_merge(
            parent::getPackageProviders($app),
            [DeferServiceProvider::class]
        );
    }

    public function testAddsTheDeferClientDirective(): void
    {
        $introspection = $this->graphQL(/** @lang GraphQL */ '
        query IntrospectionQuery {
          __schema {
            directives {
              name
            }
          }
        }
        ');

        $this->assertContains(
            'defer',
            $introspection->json('data.__schema.directives.*.name')
        );
    }

    public function testCanDeferFields(): void
    {
        $this->mockResolver([
            'name' => 'John Doe',
            'parent' => [
                'name' => 'Jane Doe',
            ],
        ]);

        $this->schema = /** @lang GraphQL */ '
        type User {
            name: String!
            parent: User
        }

        type Query {
            user: User @mock
        }
        ';

        $chunks = $this->streamGraphQL(/** @lang GraphQL */ '
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

    public function testCanDeferNestedFields(): void
    {
        $data = [
            'name' => 'John Doe',
            'parent' => [
                'name' => 'Jane Doe',
                'parent' => [
                    'name' => 'Mr. Smith',
                ],
            ],
        ];
        $this->mockResolver($data);

        $this->schema = /** @lang GraphQL */ '
        type User {
            name: String!
            parent: User
        }

        type Query {
            user: User @mock
        }
        ';

        $chunks = $this->streamGraphQL(/** @lang GraphQL */ '
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

        $this->assertSame($data['name'], Arr::get($chunks[0], 'data.user.name'));
        $this->assertNull(Arr::get($chunks[0], 'data.user.parent'));

        $deferred = Arr::get($chunks[1], 'user.parent');
        $this->assertArrayHasKey('name', $deferred['data']);
        $this->assertSame($data['parent']['name'], $deferred['data']['name']);
        $this->assertArrayHasKey('parent', $deferred['data']);
        $this->assertNull($deferred['data']['parent']);

        $nestedDeferred = Arr::get($chunks[2], 'user.parent.parent');
        $this->assertArrayHasKey('name', $nestedDeferred['data']);
        $this->assertSame($data['parent']['parent']['name'], $nestedDeferred['data']['name']);
    }

    public function testCanDeferNestedFieldsOnMutations(): void
    {
        $this->mockResolver([
            'name' => 'John Doe',
            'parent' => [
                'name' => 'Jane Doe',
            ],
        ]);

        $this->schema = /** @lang GraphQL */ '
        type User {
            name: String!
            parent: User
        }

        type Query {
            user: User @mock
        }

        type Mutation {
            updateUser(name: String!): User @mock
        }
        ';

        $chunks = $this->streamGraphQL(/** @lang GraphQL */ '
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

    public function testCanDeferListFields(): void
    {
        $data = [
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
        $this->mockResolver($data);

        $this->schema = /** @lang GraphQL */ '
        type Post {
            title: String
            author: User
        }

        type User {
            name: String!
        }

        type Query {
            posts: [Post] @mock
        }
        ';

        $chunks = $this->streamGraphQL(/** @lang GraphQL */ '
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
        $this->assertSame($data[0]['author']['name'], Arr::get($deferredPost1, 'name'));

        $deferredPost2 = $chunks[1]['posts.1.author']['data'];
        $this->assertSame($data[1]['author']['name'], Arr::get($deferredPost2, 'name'));
    }

    public function testCanDeferGroupedListFields(): void
    {
        $data = [
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
        $this->mockResolver($data);

        $this->schema = /** @lang GraphQL */ '
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
            posts: [Post] @mock
        }
        ';

        $chunks = $this->streamGraphQL(/** @lang GraphQL */ '
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
        $this->assertSame($data[0]['author']['name'], Arr::get($deferredPost1, 'name'));

        $deferredComment1 = $chunks[1]['posts.0.comments']['data'];
        $this->assertCount(1, $deferredComment1);
        $this->assertSame($data[0]['comments'][0]['message'], Arr::get($deferredComment1[0], 'message'));

        $deferredPost2 = $chunks[1]['posts.1.author']['data'];
        $this->assertSame($data[1]['author']['name'], Arr::get($deferredPost2, 'name'));

        $deferredComment2 = $chunks[1]['posts.1.comments']['data'];
        $this->assertCount(1, $deferredComment2);
        $this->assertSame($data[1]['comments'][0]['message'], Arr::get($deferredComment2[0], 'message'));
    }

    public function testCancelsDefermentAfterMaxExecutionTime(): void
    {
        $data = [
            'name' => 'John Doe',
            'parent' => [
                'name' => 'Jane Doe',
                'parent' => [
                    'name' => 'Mr. Smith',
                ],
            ],
        ];
        $this->mockResolver($data);

        $this->schema = /** @lang GraphQL */ '
        type User {
            name: String!
            parent: User
        }

        type Query {
            user: User @mock
        }
        ';

        /** @var \Nuwave\Lighthouse\Defer\Defer $defer */
        $defer = app(Defer::class);
        // Set max execution time to now so we immediately resolve deferred fields
        $defer->setMaxExecutionTime(microtime(true));

        $chunks = $this->streamGraphQL(/** @lang GraphQL */ '
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

        $this->assertSame($data['name'], Arr::get($chunks[0], 'data.user.name'));
        $this->assertNull(Arr::get($chunks[0], 'data.user.parent'));

        $deferred = Arr::get($chunks[1], 'user.parent');
        $this->assertArrayHasKey('name', $deferred['data']);
        $this->assertSame($data['parent']['name'], $deferred['data']['name']);
        $this->assertArrayHasKey('parent', $deferred['data']);
        $this->assertSame($data['parent']['parent']['name'], $deferred['data']['parent']['name']);
    }

    public function testCancelsDefermentAfterMaxNestedFields(): void
    {
        $data = [
            'name' => 'John Doe',
            'parent' => [
                'name' => 'Jane Doe',
                'parent' => [
                    'name' => 'Mr. Smith',
                ],
            ],
        ];
        $this->mockResolver($data);

        $this->schema = /** @lang GraphQL */ '
        type User {
            name: String!
            parent: User
        }

        type Query {
            user: User @mock
        }
        ';

        /** @var \Nuwave\Lighthouse\Defer\Defer $defer */
        $defer = app(Defer::class);
        $defer->setMaxNestedFields(1);

        $chunks = $this->streamGraphQL(/** @lang GraphQL */ '
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

        $this->assertSame($data['name'], Arr::get($chunks[0], 'data.user.name'));
        $this->assertNull(Arr::get($chunks[0], 'data.user.parent'));

        $deferred = Arr::get($chunks[1], 'user.parent');
        $this->assertArrayHasKey('name', $deferred['data']);
        $this->assertSame($data['parent']['name'], $deferred['data']['name']);
        $this->assertArrayHasKey('parent', $deferred['data']);
        $this->assertSame($data['parent']['parent']['name'], $deferred['data']['parent']['name']);
    }

    public function testThrowsExceptionOnNunNullableFields(): void
    {
        $this->mockResolver([
            'name' => 'John Doe',
            'parent' => [
                'name' => 'Jane Doe',
            ],
        ]);

        $this->schema = /** @lang GraphQL */ '
        type User {
            name: String!
            parent: User!
        }

        type Query {
            user: User @mock
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
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

    public function testDoesNotDeferWithIncludeAndSkipDirectives(): void
    {
        $this->mockResolver([
            'name' => 'John Doe',
        ]);
        $this->mockResolverExpects(
            $this->never(),
            'skipped'
        );

        $this->schema = /** @lang GraphQL */ '
        directive @include(if: Boolean!) on FIELD
        directive @skip(if: Boolean!) on FIELD

        type User {
            name: String!
            parent: User @mock(key: "skipped")
        }

        type Query {
            user: User @mock
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                name
                parent @defer @include(if: false) {
                    name
                }
            }
        }
        ')->assertExactJson([
            'data' => [
                'user' => [
                    'name' => 'John Doe',
                ],
            ],
        ]);

        $this->graphQL(/** @lang GraphQL */ '
        query ($include: Boolean!, $skip: Boolean!){
            userInclude: user {
                name
                parent @defer @include(if: false) {
                    name
                }
            }
            userIncludeVariable: user {
                name
                parent @defer @include(if: $include) {
                    name
                }
            }
            userSkip: user {
                name
                parent @defer @skip(if: true) {
                    name
                }
            }
            userSkipVariable: user {
                name
                parent @defer @skip(if: $skip) {
                    name
                }
            }
        }
        ', [
            'include' => false,
            'skip' => true,
        ])->assertExactJson([
            'data' => [
                'userInclude' => [
                    'name' => 'John Doe',
                ],
                'userIncludeVariable' => [
                    'name' => 'John Doe',
                ],
                'userSkip' => [
                    'name' => 'John Doe',
                ],
                'userSkipVariable' => [
                    'name' => 'John Doe',
                ],
            ],
        ]);
    }

    public function testRequiresDeferDirectiveOnAllFieldDeclarations(): void
    {
        $data = [
            'name' => 'John Doe',
            'parent' => [
                'name' => 'Jane Doe',
            ],
        ];
        $this->mockResolver($data);

        $this->schema = /** @lang GraphQL */ '
        type User {
            name: String!
            parent: User
        }

        type Query {
            user: User @mock
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        fragment UserWithParent on User {
            name
            parent {
                name
            }
        }
        query {
            user {
                ...UserWithParent
                parent @defer {
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'user' => $data,
            ],
        ]);
    }

    public function testThrowsIfTryingToDeferRootMutationFields(): void
    {
        $this->mockResolverExpects(
            $this->never()
        );

        $this->schema = /** @lang GraphQL */ '
        type User {
            name: String!
            parent: User
        }

        type Mutation {
            updateUser(name: String!): User @mock
        }
        '.self::PLACEHOLDER_QUERY;

        $this->graphQL(/** @lang GraphQL */ '
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

    public function testDoesNotDeferFieldsIfFalse(): void
    {
        $data = [
            'name' => 'John Doe',
            'parent' => [
                'name' => 'Jane Doe',
            ],
        ];
        $this->mockResolver($data);

        $this->schema = /** @lang GraphQL */ '
        type User {
            name: String!
            parent: User
        }

        type Query {
            user: User @mock
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
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
                'user' => $data,
            ],
        ]);
    }

    public function testIncludesErrorsForDeferredFields(): void
    {
        $this->mockResolver([
            'name' => 'John Doe',
            'parent' => [
                'name' => 'Jane Doe',
            ],
        ]);

        $this->mockResolver(
            function () {
                throw new Error('deferred_exception');
            },
            'throw'
        );

        $this->schema = /** @lang GraphQL */ '
        type User {
            name: String!
            parent: User @mock(key: "throw")
        }

        type Query {
            user: User @mock
        }
        ';

        $chunks = $this->streamGraphQL(/** @lang GraphQL */ '
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
}
