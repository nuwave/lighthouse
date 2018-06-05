<?php


namespace Tests\Integration;


use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class GraphQlTest extends DBTestCase
{
    use RefreshDatabase;

    /** @test */
    public function itCanResolveQuery()
    {
        /** @var User $user */
        $user = factory(User::class)->create();
        $tasks = factory(Task::class, 5)->create([
            'user_id' => $user->getKey(),
        ]);

        $this->be($user);

        $schema = '
        type User {
            id: ID!
            name: String!
            email: String!
            created_at: String!
            updated_at: String!
            tasks: [Task!]! @hasMany
        }
        type Task {
            id: ID!
            name: String!
            created_at: String!
            updated_at: String!
            user: User! @belongsTo
        }
        type Query {
            user: User @auth
        }
        ';

        $query = '
        query UserWithTasks {
            user {
                email
                tasks {
                    name
                }
            }
        }
        ';

        graphql()->build($schema);
        $data = graphql()->execute($query);
        dd($data);
        $expected = [
            'data' => [
                'user' => [
                    'email' => $user->email,
                    'tasks' => $tasks->map(function ($task) {
                        return ['name' => $task->name];
                    })->toArray(),
                ],
            ],
        ];

        $this->assertEquals($expected, $data);
    }
}