<?php

namespace Tests\Integration;

use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;
use Illuminate\Http\UploadedFile;

class GraphQLTest extends DBTestCase
{
    protected $schema = '
    scalar Upload @scalar(class: "Nuwave\\\\Lighthouse\\\\Schema\\\\Types\\\\Scalars\\\\Upload")
    
    type User {
        id: ID!
        name: String!
        email: String!
        created_at: String!
        updated_at: String!
        tasks: [Task!]! @hasMany
        avatar: Avatar @hasOne
    }
    
    type Task {
        id: ID!
        name: String!
        created_at: String!
        updated_at: String!
        user: User! @belongsTo
    }
    
    type Avatar {
        id: ID!
        url: String!
    }
    
    type Query {
        user: User @auth
    }
    
    type Mutation {
        uploadAvatar(user: ID!, file: Upload!): Avatar @field(resolver: "Tests\\\\Utils\\\\Mutations\\\\Upload@resolve")
    }
    ';

    /**
     * The user that shall make the requests.
     *
     * @var \Tests\Utils\Models\User
     */
    protected $user;

    /**
     * Tasks associated with the current user.
     *
     * @var \Illuminate\Support\Collection<\Tests\Utils\Models\Task>
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
    public function itResolvesQueryViaPostRequest(): void
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
    public function itResolvesQueryViaMultipartRequest(): void
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
        $this->postGraphQLMultipart(
            [
                'operations' => [
                    'query' => $query,
                    'variables' => [],
                ],
                'map' => [],
            ]
        )->assertJson([
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
    public function itResolvesQueryViaGetRequest(): void
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
    public function itCanResolveBatchedQueries(): void
    {
        $this->postGraphQL([
            [
                'query' => '
                    {
                        user {
                            email
                        }
                    }
                    ',
            ],
            [
                'query' => '
                    {
                        user {
                            name
                        }
                    }
                    ',
            ],
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
    public function itResolvesNamedOperation(): void
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
            'operationName' => 'User',
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
    public function itRejectsInvalidQuery(): void
    {
        $result = $this->query('
        {
            nonExistingField
        }
        ');

        $this->assertContains(
            'nonExistingField',
            $result->jsonGet('errors.0.message')
        );
    }

    /**
     * @test
     */
    public function itIgnoresInvalidJSONVariables(): void
    {
        $result = $this->postGraphQL([
            'query' => '{}',
            'variables' => '{}',
        ]);

        $result->assertStatus(200);
    }

    /**
     * @test
     */
    public function itAcceptsMultipartRequests(): void
    {
        $result = $this->postGraphQLMultipart(
            [
                'operations' => [
                    'query' => '',
                    'variables' => [],
                ],
                'map' => [],
            ]
        );

        $result->assertStatus(200);
    }

    /**
     * @test
     */
    public function itResolvesUploadViaMultipartRequest(): void
    {
        $query = '
        mutation UploadAvatar($user: ID!, $file: Upload!) {
            uploadAvatar(user: $user, file: $file) {
                id
                url
            }
        }
        ';
        $res = $this->postGraphQLMultipart(
            [
                'operations' => [
                    'query' => $query,
                    'variables' => [
                        'file' => null,
                        'user' => 1,
                    ],
                ],
                'map' => [
                    '0' => ['variables.file'],
                ],
                0 => UploadedFile::fake()->create('image.jpg', 500),
            ]
        );

        $res->assertJson([
            'data' => [
                'uploadAvatar' => [
                    'id' => 123,
                    'url' => 'http://localhost.dev/image_123.jpg',
                ]
            ]
        ]);
    }
}

