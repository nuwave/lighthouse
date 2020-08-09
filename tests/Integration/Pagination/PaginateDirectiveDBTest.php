<?php

namespace Tests\Integration\Pagination;

use Illuminate\Database\Eloquent\Builder;
use Tests\DBTestCase;
use Tests\Utils\Models\Comment;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;

class PaginateDirectiveDBTest extends DBTestCase
{
    public function testCanCreateQueryPaginators(): void
    {
        factory(User::class, 3)->create();

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users: [User!]! @paginate
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(first: 2) {
                paginatorInfo {
                    count
                    total
                    currentPage
                }
                data {
                    id
                    name
                }
            }
        }
        ')->assertJson([
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

    public function testCanSpecifyCustomBuilder(): void
    {
        factory(User::class, 2)->create();

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users: [User!]! @paginate(builder: "'.$this->qualifyTestResolver('builder').'")
        }
        ';

        // The custom builder is supposed to change the sort order
        $this->graphQL(/** @lang GraphQL */ '
        {
            users(first: 1) {
                data {
                    id
                }
            }
        }
        ')->assertJson([
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

    public function testPaginateWithScopes(): void
    {
        $namedUserName = 'A named user';
        factory(User::class)->create([
            'name' => $namedUserName,
        ]);
        factory(User::class)->create([
            'name' => null,
        ]);

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users: [User!]! @paginate(scopes: ["named"])
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(first: 5) {
                paginatorInfo {
                    count
                    total
                    currentPage
                }
                data {
                    name
                }
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    'paginatorInfo' => [
                        'count' => 1,
                        'total' => 1,
                        'currentPage' => 1,
                    ],
                    'data' => [
                        [
                            'name' => $namedUserName,
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function builder(): Builder
    {
        return User::orderBy('id', 'DESC');
    }

    public function testCanCreateQueryPaginatorsWithDifferentPages(): void
    {
        $users = factory(User::class, 3)->create();
        $posts = factory(Post::class, 3)->create([
            'user_id' => $users->first()->id,
        ]);
        factory(Comment::class, 3)->create([
            'post_id' => $posts->first()->id,
        ]);

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String!
            posts: [Post!]! @paginate
        }

        type Post {
            id: ID!
            comments: [Comment!]! @paginate
        }

        type Comment {
            id: ID!
        }

        type Query {
            users: [User!]! @paginate
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
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
        ')->assertJson([
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

    public function testCanCreateQueryConnections(): void
    {
        factory(User::class, 3)->create();

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users: [User!]! @paginate(type: "relay")
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(first: 2) {
                pageInfo {
                    hasNextPage
                }
                edges {
                    node {
                        id
                        name
                    }
                }
            }
        }
        ')->assertJson([
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
        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users: [User!]! @paginate(type: "relay")
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
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
                        name
                    }
                }
            }
        }
        ')->assertJson([
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
                ],
            ],
        ])->assertJsonCount(0, 'data.users.edges');
    }

    public function testQueriesPaginationWithNoData(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Query {
            users: [User!]! @paginate
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
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
        ')->assertJson([
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
                ],
            ],
        ])->assertJsonCount(0, 'data.users.data');
    }

    public function testPaginatesWhenDefinedInTypeExtension(): void
    {
        factory(User::class, 2)->create();

        $this->schema .= /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String!
        }

        extend type Query {
            users: [User!]! @paginate(model: "User")
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(first: 1) {
                data {
                    id
                    name
                }
            }
        }
        ')->assertJsonCount(1, 'data.users.data');
    }

    public function testCanHaveADefaultPaginationCount(): void
    {
        factory(User::class, 3)->create();

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users: [User!]! @paginate(defaultCount: 2)
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            users {
                paginatorInfo {
                    count
                    total
                    currentPage
                }
                data {
                    id
                    name
                }
            }
        }
        ')->assertJson([
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

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users: [User!] @paginate
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            users {
                data {
                    id
                }
            }
        }
        ')->assertJsonCount($defaultCount, 'data.users.data');
    }
}
