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
     * Auth user.
     *
     * @var User
     */
    protected $user;

    /**
     * User assigned tasks.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $tasks;

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('lighthouse.route_enable_get', true);
    }

    /**
     * Setup test environment.
     */
    protected function setUp()
    {
        parent::setUp();

        $this->user = factory(User::class)->create();
        $this->tasks = factory(Task::class, 5)->create([
            'user_id' => $this->user->getKey(),
        ]);
    }

    /**
     * @test
     */
    public function itCanResolveQuery()
    {
        $this->be($this->user);
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

        $data = graphql()->executeQuery($query)->toArray();
        $expected = [
            'data' => [
                'user' => [
                    'email' => $this->user->email,
                    'tasks' => $this->tasks->map(function ($task) {
                        return ['name' => $task->name];
                    })->toArray(),
                ],
            ],
        ];

        $this->assertEquals($expected, $data);
    }

    /**
     * @test
     */
    public function itCanResolveQueryThroughController()
    {
        $this->be($this->user);
        $data = $this->queryViaHttp('
        query UserWithTasks {
            user {
                email
                tasks {
                    name
                }
            }
        }
        ');

        $expected = [
            'data' => [
                'user' => [
                    'email' => $this->user->email,
                    'tasks' => $this->tasks->map(function ($task) {
                        return ['name' => $task->name];
                    })->toArray(),
                ],
            ],
        ];

        $this->assertEquals($expected, $data);
    }

    /**
     * @test
     */
    public function itCanResolveQueryThroughControllerViaGetRequest()
    {
        $this->be($this->user);
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

        $data = $this->getJson($uri)->json();

        $expected = [
            'data' => [
                'user' => [
                    'email' => $this->user->email,
                    'tasks' => $this->tasks->map(function ($task) {
                        return ['name' => $task->name];
                    })->toArray(),
                ],
            ],
        ];

        $this->assertEquals($expected, $data);
    }

    /**
     * @test
     */
    public function itCanResolveBatchedQueries()
    {
        $this->be($this->user);

        $queries = [
            ['query' => '{ user { email } }'],
            ['query' => '{ user { name } }'],
        ];

        $data = $this->postJson('/graphql', $queries)->json();

        $expected = [
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
        ];

        $this->assertEquals($expected, $data);
    }
}
