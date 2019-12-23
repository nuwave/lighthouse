<?php

namespace Tests\Integration;

use Illuminate\Http\UploadedFile;
use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class GraphQLTest extends DBTestCase
{
    protected $schema = /* @lang GraphQL */'
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

    public function testResolvesQueryViaPostRequest(): void
    {
        $this->graphQL(/* @lang GraphQL */ '
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

    public function testResolvesQueryViaGetRequest(): void
    {
        $this->getJson(
            'graphql?'
            .http_build_query(
                ['query' => /* @lang GraphQL */ '
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

    public function testCanResolveBatchedQueries(): void
    {
        $this->postGraphQL([
            [
                'query' => /* @lang GraphQL */ '
                    {
                        user {
                            email
                        }
                    }
                    ',
            ],
            [
                'query' => /* @lang GraphQL */ '
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

    public function testResolvesNamedOperation(): void
    {
        $this->postGraphQL([
            'query' => /* @lang GraphQL */ '
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

    public function testRejectsEmptyRequest(): void
    {
        $this->postGraphQL([])
             ->assertStatus(200)
             ->assertJson([
                 [
                     'errors' => [
                         [
                             'message' => 'Syntax Error: Unexpected <EOF>',
                             'extensions' => [
                                 'category' => 'graphql',
                             ],
                         ],
                     ],
                 ],
             ]);
    }

    public function testRejectsEmptyQuery(): void
    {
        $this->graphQL(/* @lang GraphQL */ '')
             ->assertStatus(200)
             ->assertJson([
                 'errors' => [
                     [
                         'message' => 'Syntax Error: Unexpected <EOF>',
                         'extensions' => [
                             'category' => 'graphql',
                         ],
                     ],
                 ],
             ]);
    }

    public function testRejectsInvalidQuery(): void
    {
        $result = $this->graphQL(/* @lang GraphQL */ '
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

    public function testIgnoresInvalidJSONVariables(): void
    {
        $result = $this->postGraphQL([
            'query' => /* @lang GraphQL */ '{}',
            'variables' => /* @lang JSON */ '{}',
        ]);

        $result->assertStatus(200);
    }

    public function testResolvesQueryViaMultipartRequest(): void
    {
        $this->multipartGraphQL(
            [
                'operations' => /* @lang JSON */ '
                    {
                        "query": "{ user { email } }",
                        "variables": {}
                    }
                ',
                'map' => /* @lang JSON */'{}',
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
}
