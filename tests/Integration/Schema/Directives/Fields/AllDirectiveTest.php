<?php

namespace Tests\Integration\Schema\Directives\Fields;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;

class AllDirectiveTest extends DBTestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function itCanGetAllModelsAsRootField()
    {
        factory(User::class, 2)->create();

        $schema = '
        type User {
            id: ID!
            name: String!
        }
        
        type Query {
            users: [User!]! @all(model: "User")
        }
        ';
        $query = '
        {
            users {
                id
                name
            }
        }
        ';
        $result = $this->execute($schema, $query);

        $this->assertCount(2, array_get($result, 'data.users'));
    }

    /**
     * @test
     */
    public function itCanGetAllAsNestedField()
    {
        $users = factory(User::class, 1)->create();
        $userId = $users->first()->id;
        factory(Post::class, 2)->create([
            'user_id' => $userId,
        ]);

        $schema = '
        type User {
            posts: [Post!]! @all(model: "Post")
        }

        type Post {
            id: ID!
        }

        type Query {
            users: [User!]! @all(model: "User")
        }
        ';
        $query = '
        {
            users {
                posts {
                    id
                }
            }
        }
        ';
        $result = $this->execute($schema, $query);

        $this->assertCount(1, array_get($result, 'data.users'));
        $this->assertCount(2, array_get($result, 'data.users.0.posts'));
    }

    /**
     * @test
     */
    public function itCanGetAllModelsFiltered()
    {
        $users = factory(User::class, 3)->create();
        $userName = $users->first()->name;

        $schema = '
        type User {
            id: ID!
            name: String!
        }
        
        type Query {
            users(name: String @neq): [User!]! @all
        }
        ';
        $query = '
        {
            users(name: "'.$userName.'") {
                id
                name
            }
        }
        ';
        $result = $this->execute($schema, $query);

        $this->assertCount(2, array_get($result, 'data.users'));
    }
}
