<?php declare(strict_types=1);

namespace Tests\Integration\Execution\MutationExecutor;

use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

final class BelongsToDirectiveOnInputFieldTest extends DBTestCase
{
    protected string $schema = /** @lang GraphQL */ <<<'GRAPHQL'
    type Task {
        id: ID!
        name: String!
        user: User @belongsTo
    }

    type User {
        id: ID!
        name: String!
    }

    type Post {
        id: ID!
        title: String!
        task: Task @belongsTo
    }

    type Mutation {
        createTask(input: CreateTaskInput! @spread): Task @create
        updateTask(input: UpdateTaskInput! @spread): Task @update
        upsertTask(input: UpsertTaskInput! @spread): Task @upsert
        createPost(input: CreatePostInput! @spread): Post @create
    }

    input CreateTaskInput {
        name: String!
        user: CreateUserRelation @belongsTo
    }

    input CreateUserRelation {
        connect: ID
        create: CreateUserInput
    }

    input CreateUserInput {
        name: String!
    }

    input UpdateTaskInput {
        id: ID!
        name: String
        user: UpdateUserRelation @belongsTo
    }

    input UpdateUserRelation {
        connect: ID
        create: CreateUserInput
        update: UpdateUserInput
        disconnect: Boolean
        delete: Boolean
    }

    input UpdateUserInput {
        id: ID!
        name: String
    }

    input UpsertTaskInput {
        id: ID
        name: String!
        user: UpsertUserRelation @belongsTo
    }

    input UpsertUserRelation {
        connect: ID
        create: CreateUserInput
        update: UpdateUserInput
        upsert: UpsertUserInput
        disconnect: Boolean
        delete: Boolean
    }

    input UpsertUserInput {
        id: ID
        name: String
    }

    input CreatePostInput {
        title: String!
        task: CreateTaskRelation @belongsTo
        owner: CreateUserRelation @belongsTo(relation: "user")
    }

    input CreateTaskRelation {
        connect: ID
    }
    GRAPHQL . self::PLACEHOLDER_QUERY;

    public function testCreateWithConnectBelongsTo(): void
    {
        $user = factory(User::class)->create();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            createTask(input: {
                name: "foo"
                user: {
                    connect: 1
                }
            }) {
                id
                name
                user {
                    id
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'createTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'user' => [
                        'id' => '1',
                    ],
                ],
            ],
        ]);
    }

    public function testCreateWithNewBelongsTo(): void
    {
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            createTask(input: {
                name: "foo"
                user: {
                    create: {
                        name: "New User"
                    }
                }
            }) {
                id
                name
                user {
                    id
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'createTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'user' => [
                        'id' => '1',
                    ],
                ],
            ],
        ]);
    }

    public function testUpdateWithConnectBelongsTo(): void
    {
        $task = factory(Task::class)->create();
        $user = factory(User::class)->create();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            updateTask(input: {
                id: 1
                name: "updated"
                user: {
                    connect: 1
                }
            }) {
                id
                name
                user {
                    id
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'updateTask' => [
                    'id' => '1',
                    'name' => 'updated',
                    'user' => [
                        'id' => '1',
                    ],
                ],
            ],
        ]);
    }

    public function testUpdateWithCreateBelongsTo(): void
    {
        $task = factory(Task::class)->create();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            updateTask(input: {
                id: 1
                name: "updated"
                user: {
                    create: {
                        name: "New User"
                    }
                }
            }) {
                id
                name
                user {
                    id
                    name
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'updateTask' => [
                    'id' => '1',
                    'name' => 'updated',
                    'user' => [
                        'id' => '1',
                        'name' => 'New User',
                    ],
                ],
            ],
        ]);
    }

    public function testUpdateAndDisconnectBelongsTo(): void
    {
        $user = factory(User::class)->create();
        $task = factory(Task::class)->make();
        $task->user()->associate($user);
        $task->save();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            updateTask(input: {
                id: 1
                name: "updated"
                user: {
                    disconnect: true
                }
            }) {
                id
                name
                user {
                    id
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'updateTask' => [
                    'id' => '1',
                    'name' => 'updated',
                    'user' => null,
                ],
            ],
        ]);
    }

    public function testUpsertCreatesWithConnectBelongsTo(): void
    {
        $user = factory(User::class)->create();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            upsertTask(input: {
                name: "foo"
                user: {
                    connect: 1
                }
            }) {
                id
                name
                user {
                    id
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'upsertTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'user' => [
                        'id' => '1',
                    ],
                ],
            ],
        ]);
    }

    public function testUpsertUpdatesWithConnectBelongsTo(): void
    {
        $task = factory(Task::class)->create();
        $user = factory(User::class)->create();

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            upsertTask(input: {
                id: {$task->id}
                name: "updated"
                user: {
                    connect: {$user->id}
                }
            }) {
                id
                name
                user {
                    id
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'upsertTask' => [
                    'id' => "{$task->id}",
                    'name' => 'updated',
                    'user' => [
                        'id' => "{$user->id}",
                    ],
                ],
            ],
        ]);
    }

    public function testCreateWithNotNullForeignKeyConnect(): void
    {
        $task = factory(Task::class)->create();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            createPost(input: {
                title: "My Post"
                task: {
                    connect: 1
                }
            }) {
                id
                title
                task {
                    id
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'createPost' => [
                    'id' => '1',
                    'title' => 'My Post',
                    'task' => [
                        'id' => '1',
                    ],
                ],
            ],
        ]);
    }

    public function testCreateWithMismatchedFieldNameUsingRelationArg(): void
    {
        $task = factory(Task::class)->create();
        $user = factory(User::class)->create();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            createPost(input: {
                title: "My Post"
                task: {
                    connect: 1
                }
                owner: {
                    connect: 1
                }
            }) {
                id
                title
                task {
                    id
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'createPost' => [
                    'id' => '1',
                    'title' => 'My Post',
                    'task' => [
                        'id' => '1',
                    ],
                ],
            ],
        ]);

        $post = \Tests\Utils\Models\Post::findOrFail(1);
        $this->assertSame($user->id, $post->user_id);
    }
}
