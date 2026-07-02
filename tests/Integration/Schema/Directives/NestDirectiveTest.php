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
        User::saving(static function () use (&$savingCount): void {
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

    public function testSiblingNestBlocksWithSameChildNameLastWins(): void
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

    public function testNullableNestWithNullValue(): void
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
                name: "No Nest"
                nested: null
            }) {
                name
                tasks {
                    name
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'createUser' => [
                    'name' => 'No Nest',
                    'tasks' => [],
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

    /** Multiple @create(relation: "tasks") children inside one @nest block — all resolve after parent save. */
    public function testNestWithMultipleHasManyChildren(): void
    {
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Mutation {
            createUser(input: CreateUserInput! @spread): User @create
        }

        input CreateUserInput {
            name: String!
            operations: TaskOperationsInput @nest
        }

        input TaskOperationsInput {
            firstTask: CreateTaskInput @create(relation: "tasks")
            secondTask: CreateTaskInput @create(relation: "tasks")
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
                name: "Multi-child"
                operations: {
                    firstTask: {
                        name: "First task"
                    }
                    secondTask: {
                        name: "Second task"
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
                    'name' => 'Multi-child',
                    'tasks' => [
                        ['name' => 'First task'],
                        ['name' => 'Second task'],
                    ],
                ],
            ],
        ]);
    }

    /** @update + @nest should still work — covers the argPartitioner change in ModelMutationDirective. */
    public function testUpdateWithNest(): void
    {
        $user = new User();
        $user->name = 'Original';
        $user->save();

        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Mutation {
            updateUser(input: UpdateUserInput! @spread): User @update
        }

        input UpdateUserInput {
            id: ID!
            nested: NestedUpdateInput @nest
        }

        input NestedUpdateInput {
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
        mutation ($id: ID!) {
            updateUser(input: {
                id: $id
                nested: {
                    newTask: {
                        name: "Added via update+nest"
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
        GRAPHQL, [
            'id' => $user->id,
        ])->assertJson([
            'data' => [
                'updateUser' => [
                    'id' => (string) $user->id,
                    'name' => 'Original',
                    'tasks' => [
                        ['name' => 'Added via update+nest'],
                    ],
                ],
            ],
        ]);
    }

    /** Implicit BelongsTo detection still works alongside @nest children. */
    public function testImplicitBelongsToCoexistsWithNest(): void
    {
        $task = new Task();
        $task->name = 'Existing';
        $task->save();

        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Mutation {
            createUser(input: CreateUserInput! @spread): User @create
        }

        input CreateUserInput {
            name: String!
            tasks: TaskOps @nest
        }

        input TaskOps {
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
                name: "Has tasks via nest"
                tasks: {
                    newTask: {
                        name: "Nested task"
                    }
                }
            }) {
                name
                tasks {
                    name
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'createUser' => [
                    'name' => 'Has tasks via nest',
                    'tasks' => [
                        ['name' => 'Nested task'],
                    ],
                ],
            ],
        ]);
    }

    /** @nest argument name matches a relation name on the model — must not confuse partitioner. */
    public function testNestArgumentNamedLikeRelation(): void
    {
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Mutation {
            createUser(input: CreateUserInput! @spread): User @create
        }

        input CreateUserInput {
            name: String!
            tasks: TaskNestInput @nest
        }

        input TaskNestInput {
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
                name: "Ambiguous name"
                tasks: {
                    newTask: {
                        name: "Created through nest"
                    }
                }
            }) {
                name
                tasks {
                    name
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'createUser' => [
                    'name' => 'Ambiguous name',
                    'tasks' => [
                        ['name' => 'Created through nest'],
                    ],
                ],
            ],
        ]);
    }

    /** @upsert inside @nest for an existing HasMany child — proves existing behavior isn't broken. */
    public function testNestWithHasManyUpsertExistingChild(): void
    {
        $user = new User();
        $user->name = 'Parent';
        $user->save();

        $task = new Task();
        $task->name = 'Original name';
        $task->user()->associate($user);
        $task->save();

        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Mutation {
            updateUser(input: UpdateUserInput! @spread): User @update
        }

        input UpdateUserInput {
            id: ID!
            nested: NestedInput @nest
        }

        input NestedInput {
            tasks: UpsertTaskInput @upsert(relation: "tasks")
        }

        input UpsertTaskInput {
            id: ID
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
        mutation ($userId: ID!, $taskId: ID!) {
            updateUser(input: {
                id: $userId
                nested: {
                    tasks: {
                        id: $taskId
                        name: "Updated name"
                    }
                }
            }) {
                id
                tasks {
                    id
                    name
                }
            }
        }
        GRAPHQL, [
            'userId' => $user->id,
            'taskId' => $task->id,
        ])->assertJson([
            'data' => [
                'updateUser' => [
                    'id' => (string) $user->id,
                    'tasks' => [
                        [
                            'id' => (string) $task->id,
                            'name' => 'Updated name',
                        ],
                    ],
                ],
            ],
        ]);
    }
}
