<?php

namespace Tests\Integration\Schema\Directives\Fields;

use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;
use Tests\Utils\Models\Comment;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PaginateDirectiveTest extends DBTestCase
{
    use RefreshDatabase;

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
            users: [User!]! @paginate(type: "paginator" model: "User")
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

        $result = $this->queryAndReturnResult($schema, $query);
        $this->assertEquals(5, array_get($result->data, 'users.paginatorInfo.count'));
        $this->assertEquals(10, array_get($result->data, 'users.paginatorInfo.total'));
        $this->assertEquals(1, array_get($result->data, 'users.paginatorInfo.currentPage'));
        $this->assertCount(5, array_get($result->data, 'users.data'));
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
            posts: [Post!]! @paginate(type: "paginator" model: "Post")
        }

        type Post {
            id: ID!
            comments: [Comment!]! @paginate(type: "paginator" model: "Comment")
        }

        type Comment {
            id: ID!
        }

        type Query {
            users: [User!]! @paginate(type: "paginator" model: "User")
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

        $result = $this->queryAndReturnResult($schema, $query);
        $this->assertEquals(1, array_get($result->data, 'users.paginatorInfo.currentPage'));
        $this->assertEquals(2, array_get($result->data, 'users.data.0.posts.paginatorInfo.currentPage'));
        $this->assertEquals(3, array_get($result->data, 'users.data.0.posts.data.0.comments.paginatorInfo.currentPage'));
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
            users: [User!]! @paginate(type: "relay" model: "User")
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

        $result = $this->queryAndReturnResult($schema, $query);
        $this->assertTrue(array_get($result->data, 'users.pageInfo.hasNextPage'));
        $this->assertCount(5, array_get($result->data, 'users.edges'));
    }
}
