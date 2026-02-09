<?php declare(strict_types=1);

namespace Tests\Integration\Pagination;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Laravel\Scout\Builder as ScoutBuilder;
use Nuwave\Lighthouse\Pagination\Cursor;
use Tests\DBTestCase;
use Tests\TestsScoutEngine;
use Tests\Utils\Models\Comment;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;

final class PaginateDirectiveDBTest extends DBTestCase
{
    use TestsScoutEngine;

    public const LIMIT_FROM_CUSTOM_SCOUT_BUILDER = 123;

    public function testPaginate(): void
    {
        factory(User::class, 3)->create();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
        }

        type Query {
            users: [User!]! @paginate
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            users(first: 2) {
                paginatorInfo {
                    count
                    total
                    currentPage
                }
                data {
                    id
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'users' => [
                    'paginatorInfo' => [
                        'count' => 2,
                        'total' => 3,
                        'currentPage' => 1,
                    ],
                    'data' => [],
                ],
            ],
        ])->assertJsonCount(2, 'data.users.data');
    }

    public function testSpecifyCustomBuilder(): void
    {
        factory(User::class, 2)->create();

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        type User {
            id: ID!
        }

        type Query {
            users: [User!]! @paginate(builder: "{$this->qualifyTestResolver('builder')}")
        }
GRAPHQL;

        // The custom builder is supposed to change the sort order
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            users(first: 1) {
                data {
                    id
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'users' => [
                    'data' => [
                        [
                            'id' => '2',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testSpecifyCustomBuilderForRelation(): void
    {
        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $posts = factory(Post::class, 2)->create();
        $user->posts()->saveMany($posts);

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        type Post {
            id: ID!
        }

        type User {
            id: ID!
            posts: [Post!]! @paginate(builder: "{$this->qualifyTestResolver('builderForRelation')}")
        }

        type Query {
            user(id: ID! @eq): User @find
        }
GRAPHQL;

        // The custom builder is supposed to change the sort order
        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        {
            user(id: {$user->id}) {
                posts(first: 10) {
                    data {
                        id
                    }
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'user' => [
                    'posts' => [
                        'data' => [
                            [
                                'id' => '2',
                            ],
                            [
                                'id' => '1',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testSpecifyCustomBuilderForScoutBuilder(): void
    {
        $this->setUpScoutEngine();

        $post = factory(Post::class)->create();
        $this->assertInstanceOf(Post::class, $post);

        $this->engine->shouldReceive('map')
            ->withArgs(static fn (ScoutBuilder $builder): bool => $builder->wheres === ['id' => "{$post->id}"]
                && $builder->limit === self::LIMIT_FROM_CUSTOM_SCOUT_BUILDER)
            ->andReturn(new EloquentCollection([$post]))
            ->once();

        $first = 42;
        $page = 69;

        $this->engine->shouldReceive('paginate')
            ->with(
                \Mockery::type(ScoutBuilder::class),
                $first,
                $page,
            )
            ->andReturn(new EloquentCollection([$post]))
            ->once();

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        type Post {
            id: ID!
        }

        type Query {
            posts(
                id: ID! @eq
            ): [Post!]! @paginate(builder: "{$this->qualifyTestResolver('builderForScoutBuilder')}")
        }
GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($first: Int!, $page: Int!, $id: ID!) {
            posts(first: $first, page: $page, id: $id) {
                data {
                    id
                }
            }
        }
        GRAPHQL, [
            'first' => $first,
            'page' => $page,
            'id' => $post->id,
        ])->assertJson([
            'data' => [
                'posts' => [
                    'data' => [
                        [
                            'id' => "{$post->id}",
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testPaginateWithScopes(): void
    {
        $namedUser = factory(User::class)->make();
        $this->assertInstanceOf(User::class, $namedUser);
        $namedUser->name = 'A named user';
        $namedUser->save();

        $unnamedUser = factory(User::class)->make();
        $this->assertInstanceOf(User::class, $unnamedUser);
        $unnamedUser->name = null;
        $unnamedUser->save();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: String!
        }

        type Query {
            users: [User!]! @paginate(scopes: ["named"])
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            users(first: 5) {
                paginatorInfo {
                    count
                    total
                    currentPage
                }
                data {
                    id
                }
            }
        }
        GRAPHQL)->assertExactJson([
            'data' => [
                'users' => [
                    'paginatorInfo' => [
                        'count' => 1,
                        'total' => 1,
                        'currentPage' => 1,
                    ],
                    'data' => [
                        [
                            'id' => "{$namedUser->id}",
                        ],
                    ],
                ],
            ],
        ]);
    }

    /** @return \Illuminate\Database\Eloquent\Builder<\Tests\Utils\Models\User> */
    public static function builder(): EloquentBuilder
    {
        return User::query()
            ->orderByDesc('id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\Tests\Utils\Models\Post, \Tests\Utils\Models\User> */
    public static function builderForRelation(User $parent): Relation
    {
        return $parent->posts()
            ->orderByDesc('id');
    }

    public static function builderForScoutBuilder(): ScoutBuilder
    {
        return Post::search('great title')
            ->take(self::LIMIT_FROM_CUSTOM_SCOUT_BUILDER);
    }

    public function testCreateQueryPaginatorsWithDifferentPages(): void
    {
        $users = factory(User::class, 3)->create();

        $firstUser = $users->first();
        $this->assertInstanceOf(User::class, $firstUser);

        $posts = factory(Post::class, 3)->make();
        foreach ($posts as $post) {
            $this->assertInstanceOf(Post::class, $post);
            $post->user()->associate($firstUser);
            $post->save();
        }

        $firstPost = $posts->first();
        $this->assertInstanceOf(Post::class, $firstPost);

        foreach (factory(Comment::class, 3)->make() as $comment) {
            $this->assertInstanceOf(Comment::class, $comment);
            $comment->post()->associate($firstPost);
            $comment->save();
        }

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            posts: [Post!]! @paginate
        }

        type Post {
            comments: [Comment!]! @paginate
        }

        type Comment {
            id: ID!
        }

        type Query {
            users: [User!]! @paginate
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            users(first: 2, page: 1) {
                paginatorInfo {
                    count
                    total
                    currentPage
                }
                data {
                    posts(first: 2, page: 2) {
                        paginatorInfo {
                            count
                            total
                            currentPage
                        }
                        data {
                            comments(first: 1, page: 3) {
                                paginatorInfo {
                                    count
                                    total
                                    currentPage
                                }
                            }
                        }
                    }
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'users' => [
                    'paginatorInfo' => [
                        'currentPage' => 1,
                    ],
                    'data' => [
                        [
                            'posts' => [
                                'paginatorInfo' => [
                                    'currentPage' => 2,
                                ],
                                'data' => [
                                    [
                                        'comments' => [
                                            'paginatorInfo' => [
                                                'currentPage' => 3,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testCreateQueryConnections(): void
    {
        factory(User::class, 3)->create();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
        }

        type Query {
            users: [User!]! @paginate(type: CONNECTION)
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            users(first: 2) {
                pageInfo {
                    hasNextPage
                }
                edges {
                    node {
                        id
                    }
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'users' => [
                    'pageInfo' => [
                        'hasNextPage' => true,
                    ],
                ],
            ],
        ])->assertJsonCount(2, 'data.users.edges');
    }

    public function testQueriesConnectionWithNoData(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
        }

        type Query {
            users: [User!]! @paginate(type: CONNECTION)
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            users(first: 5) {
                pageInfo {
                    count
                    currentPage
                    endCursor
                    hasNextPage
                    hasPreviousPage
                    lastPage
                    startCursor
                    total
                }
                edges {
                    node {
                        id
                    }
                }
            }
        }
        GRAPHQL)->assertExactJson([
            'data' => [
                'users' => [
                    'pageInfo' => [
                        'count' => 0,
                        'currentPage' => 1,
                        'endCursor' => null,
                        'hasNextPage' => false,
                        'hasPreviousPage' => false,
                        'lastPage' => 1,
                        'startCursor' => null,
                        'total' => 0,
                    ],
                    'edges' => [],
                ],
            ],
        ]);
    }

    public function testQueriesPaginationWithNoData(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
        }

        type Query {
            users: [User!]! @paginate
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            users(first: 5) {
                paginatorInfo {
                    count
                    currentPage
                    firstItem
                    hasMorePages
                    lastItem
                    lastPage
                    perPage
                    total
                }
                data {
                    id
                }
            }
        }
        GRAPHQL)->assertExactJson([
            'data' => [
                'users' => [
                    'paginatorInfo' => [
                        'count' => 0,
                        'currentPage' => 1,
                        'firstItem' => null,
                        'hasMorePages' => false,
                        'lastItem' => null,
                        'lastPage' => 1,
                        'perPage' => 5,
                        'total' => 0,
                    ],
                    'data' => [],
                ],
            ],
        ]);
    }

    public function testQueriesFirst0(): void
    {
        $amount = 3;
        factory(User::class, $amount)->create();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
        }

        type Query {
            users: [User!]! @paginate
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            users(first: 0) {
                paginatorInfo {
                    count
                    currentPage
                    firstItem
                    hasMorePages
                    lastItem
                    lastPage
                    perPage
                    total
                }
                data {
                    id
                }
            }
        }
        GRAPHQL)->assertExactJson([
            'data' => [
                'users' => [
                    'paginatorInfo' => [
                        'count' => 0,
                        'currentPage' => 1,
                        'firstItem' => null,
                        'hasMorePages' => false,
                        'lastItem' => null,
                        'lastPage' => 0,
                        'perPage' => 0,
                        'total' => $amount,
                    ],
                    'data' => [],
                ],
            ],
        ]);
    }

    public function testQueriesPaginationWithoutPaginatorInfo(): void
    {
        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
        }

        type Query {
            users: [User!]! @paginate
        }
        GRAPHQL;

        $this->assertQueryCountMatches(1, function () use ($user): void {
            $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                users(first: 1) {
                    data {
                        id
                    }
                }
            }
            GRAPHQL)->assertJson([
                'data' => [
                    'users' => [
                        'data' => [
                            [
                                'id' => $user->id,
                            ],
                        ],
                    ],
                ],
            ])->assertJsonCount(1, 'data.users.data');
        });
    }

    public function testQueriesConnectionWithoutPageInfo(): void
    {
        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
        }

        type Query {
            users: [User!]! @paginate(type: CONNECTION)
        }
        GRAPHQL;

        $this->assertQueryCountMatches(1, function () use ($user): void {
            $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                users(first: 1) {
                    edges {
                        node {
                            id
                        }
                    }
                }
            }
            GRAPHQL)->assertJson([
                'data' => [
                    'users' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => $user->id,
                                ],
                            ],
                        ],
                    ],
                ],
            ])->assertJsonCount(1, 'data.users.edges');
        });
    }

    public function testQueriesConnectionPageOffset(): void
    {
        $users = factory(User::class, 3)->create();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
        }

        type Query {
            users: [User!]! @paginate(type: CONNECTION)
        }
        GRAPHQL;

        $this->assertQueryCountMatches(2, function () use ($users): void {
            $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            query ($after: String!) {
                users(first: 2, after: $after) {
                    pageInfo {
                      hasNextPage
                      hasPreviousPage
                      startCursor
                      endCursor
                      total
                      count
                      currentPage
                      lastPage
                    }
                    edges {
                        node {
                            id
                        }
                    }
                }
            }
            GRAPHQL, [
                'after' => Cursor::encode(2),
            ])->assertJson([
                'data' => [
                    'users' => [
                        'pageInfo' => [
                            'hasNextPage' => false,
                            'hasPreviousPage' => true,
                            'startCursor' => 'Mw==',
                            'endCursor' => 'Mw==',
                            'total' => 3,
                            'count' => 1,
                            'currentPage' => 2,
                            'lastPage' => 2,
                        ],
                        'edges' => [
                            [
                                'node' => [
                                    'id' => $users[2]->id,
                                ],
                            ],
                        ],
                    ],
                ],
            ])->assertJsonCount(1, 'data.users.edges');
        });
    }

    public function testQueriesConnectionPageOffsetWithoutPageInfo(): void
    {
        $users = factory(User::class, 3)->create();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
        }

        type Query {
            users: [User!]! @paginate(type: CONNECTION)
        }
        GRAPHQL;

        $cursor = Cursor::encode(2);

        $this->assertQueryCountMatches(1, function () use ($users): void {
            $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            query ($after: String!) {
                users(first: 2, after: $after) {
                    edges {
                        node {
                            id
                        }
                    }
                }
            }
            GRAPHQL, [
                'after' => Cursor::encode(2),
            ])->assertJson([
                'data' => [
                    'users' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => $users[2]->id,
                                ],
                            ],
                        ],
                    ],
                ],
            ])->assertJsonCount(1, 'data.users.edges');
        });
    }

    public function testPaginatesWhenDefinedInTypeExtension(): void
    {
        factory(User::class, 2)->create();

        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
        }

        extend type Query {
            users: [User!]! @paginate(model: "User")
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            users(first: 1) {
                data {
                    id
                }
            }
        }
        GRAPHQL)->assertJsonCount(1, 'data.users.data');
    }

    public function testDefaultPaginationCount(): void
    {
        factory(User::class, 3)->create();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
        }

        type Query {
            users: [User!]! @paginate(defaultCount: 2)
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            users {
                paginatorInfo {
                    count
                    total
                    currentPage
                }
                data {
                    id
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'users' => [
                    'paginatorInfo' => [
                        'count' => 2,
                    ],
                ],
            ],
        ])->assertJsonCount(2, 'data.users.data');
    }

    public function testDoesNotRequireDefaultCountArgIfDefinedInConfig(): void
    {
        factory(User::class, 3)->create();

        $defaultCount = 2;
        config(['lighthouse.pagination.default_count' => $defaultCount]);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users: [User!] @paginate
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            users {
                data {
                    id
                }
            }
        }
        GRAPHQL)->assertJsonCount($defaultCount, 'data.users.data');
    }

    public function testIsUnlimitedByMaxCountFromDirective(): void
    {
        config(['lighthouse.pagination.max_count' => 5]);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users: [User!]! @paginate(maxCount: null)
        }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                users(first: 10) {
                    data {
                        id
                        name
                    }
                }
            }
            GRAPHQL)
            ->assertGraphQLErrorFree();
    }

    public function testQueriesSimplePagination(): void
    {
        config(['lighthouse.pagination.default_count' => 10]);
        factory(User::class, 3)->create();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
            name: String!
        }

        type Query {
            usersPaginated: [User!] @paginate(type: PAGINATOR)
            usersSimplePaginated: [User!] @paginate(type: SIMPLE)
        }
        GRAPHQL;

        // "paginate" fires 2 queries: One for data, one for counting.
        $this->assertQueryCountMatches(2, function (): void {
            $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                usersPaginated {
                    paginatorInfo {
                        total
                    }
                    data {
                        id
                    }
                }
            }
            GRAPHQL)->assertJsonCount(3, 'data.usersPaginated.data');
        });

        // "simplePaginate" only fires one query for the data.
        $this->assertQueryCountMatches(1, function (): void {
            $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                usersSimplePaginated {
                    data {
                        id
                    }
                }
            }
            GRAPHQL)->assertJsonCount(3, 'data.usersSimplePaginated.data');
        });
    }

    public function testGetSimplePaginationAttributes(): void
    {
        config(['lighthouse.pagination.default_count' => 10]);
        factory(User::class, 3)->create();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users: [User!] @paginate(type: SIMPLE)
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            users {
                paginatorInfo {
                    count
                    currentPage
                    firstItem
                    lastItem
                    perPage
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'users' => [
                    'paginatorInfo' => [
                        'count' => 3,
                        'currentPage' => 1,
                        'firstItem' => 1,
                        'lastItem' => 3,
                        'perPage' => 10,
                    ],
                ],
            ],
        ]);
    }

    public function testPaginateWithCacheDirective(): void
    {
        $this->expectNotToPerformAssertions();
        factory(User::class, 3)->create();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
        }

        type Query {
            users: [User!]! @paginate @cache
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            users(first: 2) {
                data {
                    id
                }
            }
        }
        GRAPHQL);

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            users(first: 2) {
                paginatorInfo {
                    count
                    total
                    currentPage
                }
                data {
                    id
                }
            }
        }
        GRAPHQL);
    }

    public function testSimplePaginationWithNullPageUsesDefaultPage(): void
    {
        factory(User::class, 3)->create();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users: [User!] @paginate(type: SIMPLE)
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($page: Int) {
            users(first: 2, page: $page) {
                paginatorInfo {
                    currentPage
                    count
                }
                data {
                    id
                }
            }
        }
        GRAPHQL, [
            'page' => null,
        ])->assertJson([
            'data' => [
                'users' => [
                    'paginatorInfo' => [
                        'currentPage' => 1,
                        'count' => 2,
                    ],
                ],
            ],
        ])->assertJsonCount(2, 'data.users.data');
    }
}
