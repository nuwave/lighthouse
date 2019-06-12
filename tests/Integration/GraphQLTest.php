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
    
    type Mutation {
        upload(file: Upload!): Boolean
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

    protected function setUp(): void
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
        $this->graphQL('
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
                        )
                        ->all(),
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function itResolvesQueryViaGetRequest(): void
    {
        $this->getJson(
            'graphql?'
            .http_build_query(
                ['query' => '
                    query UserWithTasks {
                        user {
                            email
                            tasks {
                                name
                            }
                        }
                    }
                    ',
                ]
            )
        )->assertExactJson([
            'data' => [
                'user' => [
                    'email' => $this->user->email,
                    'tasks' => $this->tasks
                        ->map(function (Task $task): array {
                            return ['name' => $task->name];
                        })
                        ->all(),
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
        $result = $this->graphQL('
        {
            nonExistingField
        }
        ');

        // TODO remove as we stop supporting Laravel 5.5/PHPUnit 6
        $assertContains = method_exists($this, 'assertStringContainsString')
            ? 'assertStringContainsString'
            : 'assertContains';

        $this->{$assertContains}(
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
    public function itResolvesQueryViaMultipartRequest(): void
    {
        $this->multipartGraphQL(
            [
                'operations' => /* @lang JSON */
                    '
                    {
                        "query": "{ user { email } }",
                        "variables": {}
                    }
                ',
                'map' => /* @lang JSON */
                    '{}',
            ],
            []
        )->assertJson([
            'data' => [
                'user' => [
                    'email' => $this->user->email,
                ],
            ],
        ]);
    }

    /**
     * @test
     * https://github.com/jaydenseric/graphql-multipart-request-spec#single-file
     */
    public function itResolvesUploadViaMultipartRequest(): void
    {
        $this->multipartGraphQL(
            [
                'operations' => /* @lang JSON */
                    '
                    {
                        "query": "mutation Upload($file: Upload!) { upload(file: $file) }",
                        "variables": {
                            "file": null
                        }
                    }
                ',
                'map' => /* @lang JSON */
                    '
                    {
                        "0": ["variables.file"]
                    }
                ',
            ],
            [
                '0' => UploadedFile::fake()->create('image.jpg', 500),
            ]
        )->assertJson([
            'data' => [
                'upload' => true,
            ],
        ]);
    }

    /**
     * @test
     * https://github.com/jaydenseric/graphql-multipart-request-spec#batching
     */
    public function itResolvesUploadViaBatchedMultipartRequest(): void
    {
        $this->multipartGraphQL(
            [
                'operations' => /* @lang JSON */
                    '
                    [
                        {
                            "query": "mutation Upload($file: Upload!) { upload(file: $file) }",
                            "variables": {
                                "file": null
                            }
                        },
                        {
                            "query": "mutation Upload($file: Upload!) { upload(file: $file)} ",
                            "variables": {
                                "file": null
                            }
                        }
                    ]
                ',
                'map' => /* @lang JSON */
                    '
                    {
                        "0": ["0.variables.file"],
                        "1": ["1.variables.file"]
                    }
                ',
            ],
            [
                '0' => UploadedFile::fake()->create('image.jpg', 500),
                '1' => UploadedFile::fake()->create('image.jpg', 500),
            ]
        )->assertJson([
            [
                'data' => [
                    'upload' => true,
                ],
            ],
            [
                'data' => [
                    'upload' => true,
                ],
            ],
        ]);
    }
}
