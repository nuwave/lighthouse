<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

final class NestDirectiveTest extends DBTestCase
{
    public function testNestDelegates(): void
    {
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Mutation {
            createUser(
                name: String
                tasks: UserTasksOperations @nest
            ): User @create
        }

        input UserTasksOperations {
            newTask: CreateTaskInput @create(relation: "tasks")
        }

        input CreateTaskInput {
            name: String
        }

        type Task {
            name: String!
        }

        type User {
            name: String
            tasks: [Task!]! @hasMany
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            createUser(
                name: "foo"
                tasks: {
                    newTask: {
                        name: "Uniq"
                    }
                }
            ) {
                name
                tasks {
                    name
                }
            }
        }
        GRAPHQL)->assertExactJson([
            'data' => [
                'createUser' => [
                    'name' => 'foo',
                    'tasks' => [
                        [
                            'name' => 'Uniq',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testNestWithBelongsToUpsert(): void
    {
        $task = new Task();
        $task->name = 'Existing task';
        $task->save();

        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Mutation {
            createPost(input: CreatePostInput! @spread): Post @create
        }

        input CreatePostInput {
            title: String!
            nested: NestedPostInput @nest
        }

        input NestedPostInput {
            task: UpsertTaskInput @upsert
        }

        input UpsertTaskInput {
            id: ID
            name: String!
        }

        type Post {
            id: ID!
            title: String!
            task: Task @belongsTo
        }

        type Task {
            id: ID!
            name: String!
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation ($taskId: ID!) {
            createPost(input: {
                title: "Post with nested belongsTo"
                nested: {
                    task: {
                        id: $taskId
                        name: "Updated task"
                    }
                }
            }) {
                id
                title
                task {
                    id
                    name
                }
            }
        }
        GRAPHQL, [
            'taskId' => $task->id,
        ])->assertJson([
            'data' => [
                'createPost' => [
                    'title' => 'Post with nested belongsTo',
                    'task' => [
                        'id' => (string) $task->id,
                        'name' => 'Updated task',
                    ],
                ],
            ],
        ]);
    }

    /** Proves pre-save and post-save resolvers coexist inside a single @nest. */
    public function testNestWithPreSaveAndPostSaveChildren(): void
    {
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Mutation {
            createUser(input: CreateUserInput! @spread): User @create
        }

        input CreateUserInput {
            name: String!
            nested: NestedUserInput @nest
        }

        input NestedUserInput {
            location: LocationInput @geocode
            newTask: CreateTaskInput @create(relation: "tasks")
        }

        input LocationInput {
            lat: Float!
            lng: Float!
        }

        input CreateTaskInput {
            name: String!
        }

        type User {
            id: ID!
            name: String!
            latitude: Float
            longitude: Float
            tasks: [Task!]! @hasMany
        }

        type Task {
            id: ID!
            name: String!
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            createUser(input: {
                name: "Mixed User"
                nested: {
                    location: {
                        lat: 48.1351
                        lng: 11.5820
                    }
                    newTask: {
                        name: "Post-save task"
                    }
                }
            }) {
                id
                name
                latitude
                longitude
                tasks {
                    name
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'createUser' => [
                    'name' => 'Mixed User',
                    'latitude' => 48.1351,
                    'longitude' => 11.582,
                    'tasks' => [
                        ['name' => 'Post-save task'],
                    ],
                ],
            ],
        ]);
    }

    public function testNestDoesNotSaveParentModelMultipleTimes(): void
    {
        $savingCount = 0;
        User::saving(function () use (&$savingCount): void {
            ++$savingCount;
        });

        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Mutation {
            createUser(input: CreateUserInput! @spread): User @create
        }

        input CreateUserInput {
            name: String!
            nested: NestedUserInput @nest
        }

        input NestedUserInput {
            newTask: CreateTaskInput @create(relation: "tasks")
        }

        input CreateTaskInput {
            name: String!
        }

        type User {
            id: ID!
            name: String!
            tasks: [Task!]! @hasMany
        }

        type Task {
            id: ID!
            name: String!
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            createUser(input: {
                name: "Save Once"
                nested: {
                    newTask: {
                        name: "Post-save task"
                    }
                }
            }) {
                id
                name
                tasks {
                    name
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'createUser' => [
                    'name' => 'Save Once',
                    'tasks' => [
                        ['name' => 'Post-save task'],
                    ],
                ],
            ],
        ]);

        $this->assertSame(1, $savingCount);
    }

    public function testSiblingNestBlocksWithSameChildName(): void
    {
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Mutation {
            createUser(input: CreateUserInput! @spread): User @create
        }

        input CreateUserInput {
            name: String!
            alpha: AlphaInput @nest
            beta: BetaInput @nest
        }

        input AlphaInput {
            location: LocationInput @geocode
        }

        input BetaInput {
            location: LocationInput @geocode
        }

        input LocationInput {
            lat: Float!
            lng: Float!
        }

        type User {
            id: ID!
            name: String!
            latitude: Float
            longitude: Float
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            createUser(input: {
                name: "Sibling Nest"
                alpha: {
                    location: {
                        lat: 48.0
                        lng: 11.0
                    }
                }
                beta: {
                    location: {
                        lat: 52.0
                        lng: 13.0
                    }
                }
            }) {
                id
                name
                latitude
                longitude
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'createUser' => [
                    'name' => 'Sibling Nest',
                    'latitude' => 52.0,
                    'longitude' => 13.0,
                ],
            ],
        ]);
    }

    public function testDoubleNestedNest(): void
    {
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Mutation {
            createUser(input: CreateUserInput! @spread): User @create
        }

        input CreateUserInput {
            name: String!
            outer: OuterInput @nest
        }

        input OuterInput {
            inner: InnerInput @nest
        }

        input InnerInput {
            location: LocationInput @geocode
            newTask: CreateTaskInput @create(relation: "tasks")
        }

        input LocationInput {
            lat: Float!
            lng: Float!
        }

        input CreateTaskInput {
            name: String!
        }

        type User {
            id: ID!
            name: String!
            latitude: Float
            longitude: Float
            tasks: [Task!]! @hasMany
        }

        type Task {
            id: ID!
            name: String!
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            createUser(input: {
                name: "Deep Nested"
                outer: {
                    inner: {
                        location: {
                            lat: 52.5200
                            lng: 13.4050
                        }
                        newTask: {
                            name: "Deeply nested task"
                        }
                    }
                }
            }) {
                id
                name
                latitude
                longitude
                tasks {
                    name
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'createUser' => [
                    'name' => 'Deep Nested',
                    'latitude' => 52.52,
                    'longitude' => 13.405,
                    'tasks' => [
                        ['name' => 'Deeply nested task'],
                    ],
                ],
            ],
        ]);
    }
}
