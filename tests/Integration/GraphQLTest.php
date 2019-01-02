<?php

namespace Tests\Integration;

use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class GraphQLTest extends DBTestCase
{
    protected $schema = '
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

    /**
     * The user that shall make the requests.
     *
     * @var User
     */
    protected $user;

    /**
     * Tasks associated with the current user.
     *
     * @var \Illuminate\Support\Collection<Task>
     */
    protected $tasks;

    protected function setUp()
    {
        parent::setUp();

        $this->user = factory(User::class)->create();
        $this->tasks = factory(Task::class, 5)->create([
            'user_id' => $this->user->getKey(),
        ]);

        $this->be($this->user);
    }

    /**
     * @test
     */
    public function itResolvesQueryViaPostRequest()
    {
        $this->query('
        query UserWithTasks {
            user {
                email
                tasks {
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'email' => $this->user->email,
                    'tasks' => $this->tasks
                        ->map(
                            function (Task $task): array {
                                return ['name' => $task->name];
                            }
                        )->toArray(),
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function itResolvesQueryViaGetRequest()
    {
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
        $uri = 'graphql?'.http_build_query(['query' => $query]);

        $this->getJson($uri)->assertExactJson([
            'data' => [
                'user' => [
                    'email' => $this->user->email,
                    'tasks' => $this->tasks->map(function ($task) {
                        return ['name' => $task->name];
                    })->toArray(),
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function itCanResolveBatchedQueries()
    {
        $this->postGraphQL([
            ['query' => '{ user { email } }'],
            ['query' => '{ user { name } }'],
        ])->assertExactJson([
            [
                'data' => [
                    'user' => [
                        'email' => $this->user->email,
                    ],
                ],
            ],
            [
                'data' => [
                    'user' => [
                        'name' => $this->user->name,
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function itResolvesNamedOperation()
    {
        $this->postGraphQL([
            'query' => '
                query User {
                    user {
                        email
                    }
                }
                query User2 {
                    user {
                        name
                    }
                }
            ',
            'operationName' => 'User'
        ])->assertExactJson([
            'data' => [
                'user' => [
                    'email' => $this->user->email,
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function itRejectsInvalidQuery()
    {
        $result = $this->query('
        {
            nonExistingField
        }
        ');

        $this->assertContains(
            'nonExistingField',
            $result->json('errors.0.message')
        );
    }
}
