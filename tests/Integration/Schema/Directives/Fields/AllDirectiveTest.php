<?php

namespace Tests\Integration\Schema\Directives\Fields;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\DBTestCase;
use Tests\Utils\Models\Comment;
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
        $users = factory(User::class, 10)->create();

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
        $this->assertCount(10, $result->data['users']);
    }

    /**
     * @test
     */
    public function itCanGetAllAsNestedField()
    {
        $users = factory(User::class, 1)->create();
        $userId = $users->first()->id;
        $posts = factory(Post::class, 10)->create([
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
        $this->assertCount(1, array_get($result->data, 'users'));
        $this->assertCount(10, array_get($result->data, 'users.0.posts'));
    }
}
