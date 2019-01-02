<?php

namespace Tests\Integration\Schema\Directives\Fields;

use Tests\DBTestCase;
use Illuminate\Support\Arr;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;

class AllDirectiveTest extends DBTestCase
{
    /**
     * @test
     */
    public function itCanGetAllModelsAsRootField()
    {
        factory(User::class, 2)->create();

        $this->schema = '
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
        $result = $this->query($query);

        $this->assertCount(2, Arr::get($result, 'data.users'));
    }

    /**
     * @test
     */
    public function itCanGetAllAsNestedField()
    {
        factory(Post::class, 2)->create([
            // Do not create those, as they would create more users
            'task_id' => 1,
        ]);

        $this->schema = '
        type User {
            posts: [Post!]! @all
        }

        type Post {
            id: ID!
        }

        type Query {
            users: [User!]! @all
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
        $result = $this->query($query);

        $this->assertSame([
            'users' => [
                ['posts' => [['id' => '1'], ['id' => '2']]],
                ['posts' => [['id' => '1'], ['id' => '2']]],
            ],
        ], $result['data']);
    }

    /**
     * @test
     */
    public function itCanGetAllModelsFiltered()
    {
        $users = factory(User::class, 3)->create();
        $userName = $users->first()->name;

        $this->schema = '
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
        $result = $this->query($query);

        $this->assertCount(2, Arr::get($result, 'data.users'));
    }
}
