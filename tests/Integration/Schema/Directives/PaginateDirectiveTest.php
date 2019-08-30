<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use GraphQL\Error\Error;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;
use Tests\Utils\Models\Comment;
use Illuminate\Database\Eloquent\Builder;

class PaginateDirectiveTest extends DBTestCase
{
    /**
     * @test
     */
    public function itCanCreateQueryPaginators(): void
    {
        factory(User::class, 10)->create();

        $this->schema = '
        type User {
            id: ID!
            name: String!
        }
        
        type Query {
            users: [User!]! @paginate
        }
        ';

        $this->graphQL('
        {
            users(first: 5) {
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
                        'count' => 5,
                        'total' => 10,
                        'currentPage' => 1,
                    ],
                    'data' => [],
                ],
            ],
        ])->assertJsonCount(5, 'data.users.data');
    }

    /**
     * @test
     */
    public function itHandlesPaginationWithCountZero(): void
    {
        $this->schema = '
        type User {
            id: ID!
            name: String!
        }
        
        type Query {
            users: [User!] @paginate
        }
        ';

        $this->graphQL('
        {
            users(first: 0) {
                data {
                    id
                }
            }
        }
        ')
        ->assertJson([
            'data' => [
                'users' => null,
            ],
        ])
        ->assertErrorCategory(Error::CATEGORY_GRAPHQL);
    }

    /**
     * @test
     */
    public function itCanSpecifyCustomBuilder(): void
    {
        factory(User::class, 2)->create();

        $this->schema = '
        type User {
            id: ID!
            name: String!
        }
        
        type Query {
            users: [User!]! @paginate(builder: "Tests\\\Integration\\\Schema\\\Directives\\\PaginateDirectiveTest@builder")
        }
        ';

        // The custom builder is supposed to change the sort order
        $this->graphQL('
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

    public function builder(): Builder
    {
        return User::orderBy('id', 'DESC');
    }

    /**
     * @test
     */
    public function itCanCreateQueryPaginatorsWithDifferentPages(): void
    {
        $users = factory(User::class, 10)->create();
        $posts = factory(Post::class, 10)->create([
            'user_id' => $users->first()->id,
        ]);
        factory(Comment::class, 10)->create([
            'post_id' => $posts->first()->id,
        ]);

        $this->schema = '
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

        $this->graphQL('
        {
            users(first: 3, page: 1) {
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

    /**
     * @test
     */
    public function itCanCreateQueryConnections(): void
    {
        factory(User::class, 10)->create();

        $this->schema = '
        type User {
            id: ID!
            name: String!
        }
        
        type Query {
            users: [User!]! @paginate(type: "relay")
        }
        ';

        $this->graphQL('
        {
            users(first: 5) {
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
        ])->assertJsonCount(5, 'data.users.edges');
    }

    /**
     * @test
     */
    public function itQueriesConnectionWithNoData(): void
    {
        $this->schema = '
        type User {
            id: ID!
            name: String!
        }
        
        type Query {
            users: [User!]! @paginate(type: "relay")
        }
        ';

        $this->graphQL('
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

    /**
     * @test
     */
    public function itQueriesPaginationWithNoData(): void
    {
        $this->schema = '
        type User {
            id: ID!
        }
        
        type Query {
            users: [User!]! @paginate
        }
        ';

        $this->graphQL('
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

    /**
     * @test
     */
    public function itPaginatesWhenDefinedInTypeExtension(): void
    {
        factory(User::class, 2)->create();

        $this->schema = '
        type User {
            id: ID!
            name: String!
        }

        extend type Query {
            users: [User!]! @paginate(model: "User")
        }
        '.$this->placeholderQuery();

        $this->graphQL('
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

    /**
     * @test
     */
    public function itCanHaveADefaultPaginationCount(): void
    {
        factory(User::class, 10)->create();

        $this->schema = '
        type User {
            id: ID!
            name: String!
        }
        
        type Query {
            users: [User!]! @paginate(defaultCount: 5)
        }
        ';

        $this->graphQL('
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
                        'count' => 5,
                    ],
                ],
            ],
        ])->assertJsonCount(5, 'data.users.data');
    }

    /**
     * @test
     */
    public function itIsLimitedToMaxCountFromConfig(): void
    {
        config(['lighthouse.paginate_max_count' => 5]);

        factory(User::class, 10)->create();

        $this->schema = '
        type User {
            id: ID!
            name: String!
        }
        
        type Query {
            users1: [User!]! @paginate
            users2: [User!]! @paginate(type: "relay")
        }
        ';

        $resultFromDefaultPagination = $this->graphQL('
        {
            users1(first: 10) {
                data {
                    id
                    name
                }
            }
        }
        ');

        $this->assertSame(
            'Maximum number of 5 requested items exceeded. Fetch smaller chunks.',
            $resultFromDefaultPagination->jsonGet('errors.0.message')
        );

        $resultFromRelayPagination = $this->graphQL('
        {
            users2(first: 10) {
                edges {
                    node {
                        id
                        name
                    }
                }
            }
        }
        ');

        $this->assertSame(
            'Maximum number of 5 requested items exceeded. Fetch smaller chunks.',
            $resultFromRelayPagination->jsonGet('errors.0.message')
        );
    }

    /**
     * @test
     */
    public function itIsLimitedByMaxCountFromDirective(): void
    {
        config(['lighthouse.paginate_max_count' => 5]);

        factory(User::class, 10)->create();

        $this->schema = '
        type User {
            id: ID!
            name: String!
        }
        
        type Query {
            users1: [User!]! @paginate(maxCount: 6)
            users2: [User!]! @paginate(maxCount: 10)
        }
        ';

        $result = $this->graphQL('
        {
            users1(first: 10) {
                data {
                    id
                    name
                }
            }
        }
        ');

        $this->assertSame(
            'Maximum number of 6 requested items exceeded. Fetch smaller chunks.',
            $result->jsonGet('errors.0.message')
        );

        $this->graphQL('
        {
            users2(first: 10) {
                data {
                    id
                    name
                }
            }
        }
        ')->assertJsonCount(10, 'data.users2.data');
    }
}
