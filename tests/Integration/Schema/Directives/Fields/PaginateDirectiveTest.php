<?php

namespace Tests\Integration\Schema\Directives\Fields;

use Tests\DBTestCase;
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

        $this->query('
        {
            users(count: 5) {
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
    public function itCanSpecifyCustomBuilder(): void
    {
        factory(User::class, 2)->create();

        $this->schema = '
        type User {
            id: ID!
            name: String!
        }
        
        type Query {
            users: [User!]! @paginate(builder: "Tests\\\Integration\\\Schema\\\Directives\\\Fields\\\PaginateDirectiveTest@builder")
        }
        ';

        // The custom builder is supposed to change the sort order
        $this->query('
        {
            users(count: 1) {
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

        $this->query('
        {
            users(count: 3, page: 1) {
                paginatorInfo {
                    count
                    total
                    currentPage
                }
                data {
                    posts(count: 2, page: 2) {
                        paginatorInfo {
                            count
                            total
                            currentPage
                        }
                        data {
                            comments(count: 1, page: 3) {
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

        $this->query('
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

        $this->query('
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

        $this->query('
        {
            users(count: 5) {
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

        extend type Query @group {
            users: [User!]! @paginate(model: "User")
        }
        '.$this->placeholderQuery();

        $this->query('
        {
            users(count: 1) {
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

        $this->query('
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
}
