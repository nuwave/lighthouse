<?php

namespace Tests\Integration\Schema\Directives\Fields;

use Tests\DBTestCase;
use Illuminate\Support\Arr;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;
use Tests\Utils\Models\Comment;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Builder;

class PaginateDirectiveTest extends DBTestCase
{
    /**
     * @test
     */
    public function itCanCreateQueryPaginators()
    {
        factory(User::class, 10)->create();

        $schema = '
        type User {
            id: ID!
            name: String!
        }
        
        type Query {
            users: [User!]! @paginate
        }
        ';

        $query = '
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
        ';

        $result = $this->executeQuery($schema, $query);
        $this->assertEquals(5, Arr::get($result->data, 'users.paginatorInfo.count'));
        $this->assertEquals(10, Arr::get($result->data, 'users.paginatorInfo.total'));
        $this->assertEquals(1, Arr::get($result->data, 'users.paginatorInfo.currentPage'));
        $this->assertCount(5, Arr::get($result->data, 'users.data'));
    }

    /**
     * @test
     */
    public function itCanSpecifyCustomBuilder()
    {
        factory(User::class, 2)->create();

        $schema = '
        type User {
            id: ID!
            name: String!
        }
        
        type Query {
            users: [User!]! @paginate(builder: "Tests\\\Integration\\\Schema\\\Directives\\\Fields\\\PaginateDirectiveTest@builder")
        }
        ';

        $query = '
        {
            users(count: 1) {
                data {
                    id
                }
            }
        }
        ';

        $result = $this->execute($schema, $query);
        $this->assertSame('2', Arr::get($result, 'data.users.data.0.id'), 'The custom builder did not change the sort order correctly.');
    }

    public function builder($root, array $args, $context, ResolveInfo $resolveInfo): Builder
    {
        return User::orderBy('id', 'DESC');
    }

    /**
     * @test
     */
    public function itCanCreateQueryPaginatorsWithDifferentPages()
    {
        $users = factory(User::class, 10)->create();
        $posts = factory(Post::class, 10)->create([
            'user_id' => $users->first()->id,
        ]);
        factory(Comment::class, 10)->create([
            'post_id' => $posts->first()->id,
        ]);

        $schema = '
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
        $query = '
        {
            users(count:3 page: 1) {
                paginatorInfo {
                    count
                    total
                    currentPage
                }
                data {
                    posts(count:2 page: 2) {
                        paginatorInfo {
                            count
                            total
                            currentPage
                        }
                        data {
                            comments(count:1 page: 3) {
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
        ';
        $result = $this->execute($schema, $query);

        $users = Arr::get($result, 'data.users');

        $this->assertSame(1, Arr::get($users, 'paginatorInfo.currentPage'));
        $this->assertSame(2, Arr::get($users, 'data.0.posts.paginatorInfo.currentPage'));
        $this->assertSame(3, Arr::get($users, 'data.0.posts.data.0.comments.paginatorInfo.currentPage'));
    }

    /**
     * @test
     */
    public function itCanCreateQueryConnections()
    {
        factory(User::class, 10)->create();

        $schema = '
        type User {
            id: ID!
            name: String!
        }
        
        type Query {
            users: [User!]! @paginate(type: "relay")
        }
        ';

        $query = '
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
        ';

        $result = $this->executeQuery($schema, $query);
        $this->assertTrue(Arr::get($result->data, 'users.pageInfo.hasNextPage'));
        $this->assertCount(5, Arr::get($result->data, 'users.edges'));
    }

    /**
     * @test
     */
    public function itQueriesConnectionWithNoData()
    {
        $schema = '
        type User {
            id: ID!
            name: String!
        }
        
        type Query {
            users: [User!]! @paginate(type: "relay")
        }
        ';

        $query = '
        {
            users(first: 5) {
                pageInfo {
                    total
                    count
                    currentPage
                    lastPage
                    hasNextPage
                    hasPreviousPage
                    startCursor
                    endCursor
                }
                edges {
                    node {
                        id
                        name
                    }
                }
            }
        }
        ';

        $result = $this->executeQuery($schema, $query);
        $this->assertSame(
            [
                'total' => 0,
                'count' => 0,
                'currentPage' => 1,
                'lastPage' => 1,
                'hasNextPage' => false,
                'hasPreviousPage' => false,
                'startCursor' => null,
                'endCursor' => null,
            ],
            Arr::get($result->data, 'users.pageInfo')
        );
        $this->assertCount(0, Arr::get($result->data, 'users.edges'));
    }

    /**
     * @test
     */
    public function itQueriesPaginationWithNoData()
    {
        $schema = '
        type User {
            id: ID!
        }
        
        type Query {
            users: [User!]! @paginate
        }
        ';

        $query = '
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
        ';

        $result = $this->executeQuery($schema, $query);
        $this->assertSame(
            [
                'count' => 0,
                'currentPage' => 1,
                'firstItem' => null,
                'hasMorePages' => false,
                'lastItem' => null,
                'lastPage' => 1,
                'perPage' => 5,
                'total' => 0,
            ],
            Arr::get($result->data, 'users.paginatorInfo')
        );
        $this->assertCount(0, Arr::get($result->data, 'users.data'));
    }

    /**
     * @test
     */
    public function itPaginatesWhenDefinedInTypeExtension()
    {
        factory(User::class, 2)->create();

        $schema = '
        type User {
            id: ID!
            name: String!
        }

        extend type Query @group {
            users: [User!]! @paginate(model: "User")
        }
        '.$this->placeholderQuery();

        $query = '
        {
            users(count: 1) {
                data {
                    id
                    name
                }
            }
        }
        ';

        $result = $this->executeQuery($schema, $query);

        $this->assertCount(1, Arr::get($result->data, 'users.data'));
    }

    /** @test */
    public function itCanHaveADefaultPaginationCount()
    {
        factory(User::class, 10)->create();

        $schema = '
        type User {
            id: ID!
            name: String!
        }
        
        type Query {
            users: [User!]! @paginate(defaultCount: 5)
        }
        ';

        $query = '
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
        ';

        $result = $this->executeQuery($schema, $query);
        $this->assertEquals(5, Arr::get($result->data, 'users.paginatorInfo.count'));
        $this->assertEquals(10, Arr::get($result->data, 'users.paginatorInfo.total'));
        $this->assertCount(5, Arr::get($result->data, 'users.data'));
    }
}
