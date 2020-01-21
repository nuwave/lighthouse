<?php

namespace Tests\Integration\Execution\MutationExecutor;

use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class BelongsToTest extends DBTestCase
{
    protected $schema = /** @lang GraphQL */ '
    type Task {
        id: ID!
        name: String!
        user: User @belongsTo
    }

    type User {
        id: ID!
        name: String!
    }

    type Mutation {
        createTask(input: CreateTaskInput! @spread): Task @create
        updateTask(input: UpdateTaskInput! @spread): Task @update
        upsertTask(input: UpsertTaskInput! @spread): Task @upsert
    }

    input CreateTaskInput {
        name: String
        user: CreateUserRelation
    }

    input CreateUserRelation {
        connect: ID
        create: CreateUserInput
        update: UpdateUserInput
        upsert: UpsertUserInput
    }

    input CreateUserInput {
        name: String!
    }

    input UpdateUserInput {
        id: ID!
        name: String
    }

    input UpdateTaskInput {
        id: ID!
        name: String
        user: UpdateUserRelation
    }

    input UpdateUserRelation {
        disconnect: Boolean
        delete: Boolean
    }

    input UpsertUserInput {
        id: ID
        name: String
    }

    input UpsertTaskInput {
        id: ID
        name: String
        user: UpsertUserRelation
    }

    input UpsertUserRelation {
        connect: ID
        create: CreateUserInput
        update: UpdateUserInput
        upsert: UpsertUserInput
        disconnect: Boolean
        delete: Boolean
    }
    '.self::PLACEHOLDER_QUERY;

    public function testCanCreateAndConnectWithBelongsTo(): void
    {
        factory(User::class)->create();

        $this->graphQL(/** @lang GraphQL */ '
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
        ')->assertJson([
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

    public function testCanUpsertUsingCreateAndConnectWithBelongsTo(): void
    {
        factory(User::class)->create();

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            upsertTask(input: {
                id: 1
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
        ')->assertJson([
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

    public function testCanCreateWithNewBelongsTo(): void
    {
        $this->graphQL(/** @lang GraphQL */ '
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
        ')->assertJson([
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

    public function testCanUpsertWithNewBelongsTo(): void
    {
        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            createTask(input: {
                name: "foo"
                user: {
                    upsert: {
                        id: 1
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
        ')->assertJson([
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

    public function testUpsertBelongsToWithoutId()
    {
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            upsertTask(input: {
                name: "foo"
                user: {
                    upsert: {
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
GRAPHQL
        )->assertJson([
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

    public function testCanUpsertUsingCreateWithNewBelongsTo(): void
    {
        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            upsertTask(input: {
                id: 1
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
        ')->assertJson([
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

    public function testCanUpsertUsingCreateWithNewUpsertBelongsTo(): void
    {
        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            upsertTask(input: {
                id: 1
                name: "foo"
                user: {
                    upsert: {
                        id: 1
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
        ')->assertJson([
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

    public function testCanCreateAndUpdateBelongsTo(): void
    {
        factory(User::class)->create([
            'name' => 'foo',
        ]);

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            createTask(input: {
                name: "foo"
                user: {
                    update: {
                        id: 1
                        name: "bar"
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
        ')->assertJson([
            'data' => [
                'createTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'user' => [
                        'id' => '1',
                        'name' => 'bar',
                    ],
                ],
            ],
        ]);
    }

    public function testCanUpsertUsingCreateAndUpdateBelongsTo(): void
    {
        factory(User::class)->create([
            'name' => 'foo',
        ]);

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            upsertTask(input: {
                id: 1
                name: "foo"
                user: {
                    update: {
                        id: 1
                        name: "bar"
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
        ')->assertJson([
            'data' => [
                'upsertTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'user' => [
                        'id' => '1',
                        'name' => 'bar',
                    ],
                ],
            ],
        ]);
    }

    public function testCanUpsertUsingCreateAndUpdateUsingUpsertBelongsTo(): void
    {
        factory(User::class)->create([
            'name' => 'foo',
        ]);

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            upsertTask(input: {
                id: 1
                name: "foo"
                user: {
                    upsert: {
                        id: 1
                        name: "bar"
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
        ')->assertJson([
            'data' => [
                'upsertTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'user' => [
                        'id' => '1',
                        'name' => 'bar',
                    ],
                ],
            ],
        ]);
    }

    public function existingModelMutations()
    {
        return [
            ['Update action' => 'update'],
            ['Upsert action' => 'upsert'],
        ];
    }

    /**
     * @dataProvider existingModelMutations
     */
    public function testCanUpdateAndDisconnectBelongsTo(string $action): void
    {
        /** @var \Tests\Utils\Models\Task $task */
        $task = factory(Task::class)->create();
        $task->user()->create();

        $this->graphQL(/** @lang GraphQL */ "
        mutation {
            ${action}Task(input: {
                id: 1
                name: \"foo\"
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
        ")->assertJson([
            'data' => [
                "${action}Task" => [
                    'id' => '1',
                    'name' => 'foo',
                    'user' => null,
                ],
            ],
        ]);

        $this->assertTrue(
            User::find(1)->exists,
            'Must not delete the second model.'
        );

        $this->assertNull(
            Task::find(1)->user,
            'Must disconnect the parent relationship.'
        );
    }

    public function testCanCreateUsingUpsertAndDisconnectBelongsTo(): void
    {
        /** @var \Tests\Utils\Models\User $user */
        $user = factory(User::class)->create();
        $task = $user->tasks()->save(
            factory(Task::class)->make()
        );

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            upsertTask(input: {
                id: 1
                name: "foo"
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
        ')->assertJson([
            'data' => [
                'upsertTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'user' => null,
                ],
            ],
        ]);

        $this->assertTrue(
            User::find($user->id)->exists,
            'Must not delete the second model.'
        );

        $this->assertNull(
            $task->refresh()->user_id,
            'Must disconnect the parent relationship.'
        );
    }

    /**
     * @dataProvider existingModelMutations
     */
    public function testCanUpdateAndDeleteBelongsTo(string $action): void
    {
        /** @var \Tests\Utils\Models\User $user */
        $user = factory(User::class)->create();
        $task = $user->tasks()->save(
            factory(Task::class)->make()
        );

        $this->graphQL(/** @lang GraphQL */ "
        mutation {
            ${action}Task(input: {
                id: 1
                name: \"foo\"
                user: {
                    delete: true
                }
            }) {
                id
                name
                user {
                    id
                }
            }
        }
        ")->assertJson([
            'data' => [
                "${action}Task" => [
                    'id' => '1',
                    'name' => 'foo',
                    'user' => null,
                ],
            ],
        ]);

        $this->assertNull(
            User::find($user->id),
            'This model should be deleted.'
        );

        $this->assertNull(
            $task->refresh()->user_id,
            'Must disconnect the parent relationship.'
        );
    }

    public function testCanCreateUsingUpsertAndDeleteBelongsTo(): void
    {
        factory(User::class)->create();

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            upsertTask(input: {
                id: 1
                name: "foo"
                user: {
                    delete: true
                }
            }) {
                id
                name
                user {
                    id
                }
            }
        }
        ')->assertJson([
            'data' => [
                'upsertTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'user' => null,
                ],
            ],
        ]);

        $this->assertNotNull(
            User::find(1),
            'This model should NOT be deleted.'
        );

        $this->assertNull(
            Task::find(1)->user,
            'Must disconnect the parent relationship.'
        );
    }

    /**
     * @dataProvider existingModelMutations
     */
    public function testDoesNotDeleteOrDisconnectOnFalsyValues(string $action): void
    {
        /** @var \Tests\Utils\Models\User $user */
        $user = factory(User::class)->create();
        $task = $user->tasks()->save(
            factory(Task::class)->make()
        );

        $this->graphQL(/** @lang GraphQL */ "
        mutation {
            ${action}Task(input: {
                id: 1
                name: \"foo\"
                user: {
                    delete: null
                    disconnect: false
                }
            }) {
                id
                name
                user {
                    id
                }
            }
        }
        ")->assertJson([
            'data' => [
                "${action}Task" => [
                    'id' => '1',
                    'name' => 'foo',
                    'user' => [
                        'id' => '1',
                    ],
                ],
            ],
        ]);

        $this->assertSame(
            $user->id,
            $task->refresh()->user->id,
            'The parent relationship remains untouched.'
        );
    }
}
