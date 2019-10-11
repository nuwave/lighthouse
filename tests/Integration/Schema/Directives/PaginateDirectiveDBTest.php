<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use GraphQL\Error\Error;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;
use Tests\Utils\Models\Comment;
use Illuminate\Database\Eloquent\Builder;

class PaginateDirectiveDBTest extends DBTestCase
{
    public function testCanCreateQueryPaginators(): void
    {
        factory(User::class, 3)->create();

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

        $this->schema = '
        type User {
            id: ID!
            name: String!
        }
        
        type Query {
            users: [User!]! @paginate(builder: "' .$this->qualifyTestResolver('builder').'")
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

    public function testCanCreateQueryPaginatorsWithDifferentPages(): void
    {
        $users = factory(User::class, 3)->create();
        $posts = factory(Post::class, 3)->create([
            'user_id' => $users->first()->id,
        ]);
        factory(Comment::class, 3)->create([
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

    public function testQueriesPaginationWithNoData(): void
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

    public function testPaginatesWhenDefinedInTypeExtension(): void
    {
        factory(User::class, 2)->create();

        $this->schema .= '
        type User {
            id: ID!
            name: String!
        }

        extend type Query {
            users: [User!]! @paginate(model: "User")
        }
        ';

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

    public function testCanHaveADefaultPaginationCount(): void
    {
        factory(User::class, 3)->create();

        $this->schema = '
        type User {
            id: ID!
            name: String!
        }
        
        type Query {
            users: [User!]! @paginate(defaultCount: 2)
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
                        'count' => 2,
                    ],
                ],
            ],
        ])->assertJsonCount(2, 'data.users.data');
    }
}
