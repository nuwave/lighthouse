<?php

namespace Tests\Integration\Schema\Directives\Fields;

use Tests\DBTestCase;
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
            users2: [User!]! @paginate
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
        $this->assertEquals(5, array_get($result->data, 'users.paginatorInfo.count'));
        $this->assertEquals(10, array_get($result->data, 'users.paginatorInfo.total'));
        $this->assertEquals(1, array_get($result->data, 'users.paginatorInfo.currentPage'));
        $this->assertCount(5, array_get($result->data, 'users.data'));
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
        $this->assertSame('2', array_get($result, 'data.users.data.0.id'), 'The custom builder did not change the sort order correctly.');
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

        $users = array_get($result, 'data.users');

        $this->assertSame(1, array_get($users, 'paginatorInfo.currentPage'));
        $this->assertSame(2, array_get($users, 'data.0.posts.paginatorInfo.currentPage'));
        $this->assertSame(3, array_get($users, 'data.0.posts.data.0.comments.paginatorInfo.currentPage'));
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
        $this->assertTrue(array_get($result->data, 'users.pageInfo.hasNextPage'));
        $this->assertCount(5, array_get($result->data, 'users.edges'));
    }
}
