<?php declare(strict_types=1);

namespace Tests\Integration\Execution\MutationExecutor;

use Tests\DBTestCase;
use Tests\Utils\Models\CustomPrimaryKey;
use Tests\Utils\Models\Role;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

final class HasManyTest extends DBTestCase
{
    protected string $schema = /** @lang GraphQL */ '
    type Task {
        id: ID!
        name: String!
    }

    type User {
        id: ID!
        name: String
        tasks: [Task!]! @hasMany
    }

    type Mutation {
        createUser(input: CreateUserInput! @spread): User @create
        updateUser(input: UpdateUserInput! @spread): User @update
        upsertUser(input: UpsertUserInput! @spread): User @upsert
    }

    input CreateUserInput {
        name: String
        tasks: CreateTaskRelation
    }

    input CreateTaskRelation {
        create: [CreateTaskInput!]
        upsert: [UpsertTaskInput!]
        connect: [ID!]
    }

    input CreateTaskInput {
        name: String
    }

    input UpdateUserInput {
        id: ID!
        name: String
        tasks: UpdateTaskRelation
    }

    input UpdateTaskRelation {
        create: [CreateTaskInput!]
        update: [UpdateTaskInput!]
        upsert: [UpsertTaskInput!]
        delete: [ID!]
        connect: [ID!]
        disconnect: [ID!]
    }

    input UpdateTaskInput {
        id: ID!
        name: String
    }

    input UpsertUserInput {
        id: ID
        name: String
        tasks: UpsertTaskRelation
    }

    input UpsertTaskRelation {
        create: [CreateTaskInput!]
        update: [UpdateTaskInput!]
        upsert: [UpsertTaskInput!]
        delete: [ID!]
        connect: [ID!]
        disconnect: [ID!]
    }

    input UpsertTaskInput {
        id: ID
        name: String
    }
    ' . self::PLACEHOLDER_QUERY;

    public function testCreateWithNewHasMany(): void
    {
        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            createUser(input: {
                name: "foo"
                tasks: {
                    create: [
                        {
                            name: "bar"
                        }
                    ]
                }
            }) {
                id
                name
                tasks {
                    id
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'createUser' => [
                    'id' => '1',
                    'name' => 'foo',
                    'tasks' => [
                        [
                            'id' => '1',
                            'name' => 'bar',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testCreateWithConnectHasMany(): void
    {
        $task1 = factory(Task::class)->create();
        assert($task1 instanceof Task);

        $task2 = factory(Task::class)->create();
        assert($task2 instanceof Task);

        $this->graphQL(/** @lang GraphQL */ '
            mutation ($input: CreateUserInput!) {
                createUser(input: $input) {
                    name
                    tasks {
                        id
                        name
                    }
                }
            }
            ',
            [
                'input' => [
                    'name' => 'foo',
                    'tasks' => [
                        'connect' => [
                            $task1->id,
                            $task2->id,
                        ],
                    ],
                ],
            ],
        )->assertJson([
            'data' => [
                'createUser' => [
                    'name' => 'foo',
                    'tasks' => [
                        [
                            'id' => (string) $task1->id,
                            'name' => $task1->name,
                        ],
                        [
                            'id' => (string) $task2->id,
                            'name' => $task2->name,
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testAllowsNullOperations(): void
    {
        factory(User::class)->create();

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            updateUser(input: {
                id: 1
                name: "foo"
                tasks: {
                    create: null
                    update: null
                    upsert: null
                    delete: null
                }
            }) {
                name
                tasks {
                    id
                }
            }
        }
        ')->assertJson([
            'data' => [
                'updateUser' => [
                    'name' => 'foo',
                    'tasks' => [],
                ],
            ],
        ]);
    }

    public function testUpsertWithNewHasMany(): void
    {
        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            createUser(input: {
                name: "foo"
                tasks: {
                    upsert: [{
                        id: 1
                        name: "bar"
                    }]
                }
            }) {
                id
                name
                tasks {
                    id
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'createUser' => [
                    'id' => '1',
                    'name' => 'foo',
                    'tasks' => [
                        [
                            'id' => '1',
                            'name' => 'bar',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testUpsertHasManyWithoutId(): void
    {
        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            upsertUser(input: {
                name: "foo"
                tasks: {
                    upsert: [{
                        name: "bar"
                    }]
                }
            }) {
                id
                name
                tasks {
                    id
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'upsertUser' => [
                    'id' => '1',
                    'name' => 'foo',
                    'tasks' => [
                        [
                            'id' => '1',
                            'name' => 'bar',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testCreateUsingUpsertWithNewHasMany(): void
    {
        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            upsertUser(input: {
                id: 1
                name: "foo"
                tasks: {
                    upsert: [{
                        id: 1
                        name: "bar"
                    }]
                }
            }) {
                id
                name
                tasks {
                    id
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'upsertUser' => [
                    'id' => '1',
                    'name' => 'foo',
                    'tasks' => [
                        [
                            'id' => '1',
                            'name' => 'bar',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /** @return iterable<array{string}> */
    public static function existingModelMutations(): iterable
    {
        yield 'Update action' => ['update'];
        yield 'Upsert action' => ['upsert'];
    }

    /** @dataProvider existingModelMutations */
    public function testCreateHasMany(string $action): void
    {
        factory(User::class)->create();

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            {$action}User(input: {
                id: 1
                name: "foo"
                tasks: {
                    create: [{
                        name: "bar"
                    }]
                }
            }) {
                id
                name
                tasks {
                    id
                    name
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                "{$action}User" => [
                    'id' => '1',
                    'name' => 'foo',
                    'tasks' => [
                        [
                            'id' => '1',
                            'name' => 'bar',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /** @dataProvider existingModelMutations */
    public function testUpdateHasMany(string $action): void
    {
        $user = factory(User::class)->create();
        assert($user instanceof User);

        $user->tasks()
            ->save(
                factory(Task::class)->create(),
            );

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            {$action}User(input: {
                id: 1
                name: "foo"
                tasks: {
                    update: [{
                        id: 1
                        name: "bar"
                    }]
                }
            }) {
                id
                name
                tasks {
                    id
                    name
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                "{$action}User" => [
                    'id' => "{$user->id}",
                    'name' => 'foo',
                    'tasks' => [
                        [
                            'id' => '1',
                            'name' => 'bar',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /** @dataProvider existingModelMutations */
    public function testUpsertHasMany(string $action): void
    {
        $user = factory(User::class)->create();
        assert($user instanceof User);

        $user->tasks()
            ->save(
                factory(Task::class)->create(),
            );

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            {$action}User(input: {
                id: 1
                name: "foo"
                tasks: {
                    upsert: [{
                        id: 1
                        name: "bar"
                    }]
                }
            }) {
                id
                name
                tasks {
                    id
                    name
                }
            }
        }
GRAPHQL
        )->assertJson([
            'data' => [
                "{$action}User" => [
                    'id' => "{$user->id}",
                    'name' => 'foo',
                    'tasks' => [
                        [
                            'id' => '1',
                            'name' => 'bar',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /** @dataProvider existingModelMutations */
    public function testDeleteHasMany(string $action): void
    {
        $user = factory(User::class)->create();
        assert($user instanceof User);

        $user->tasks()
            ->save(
                factory(Task::class)->create(),
            );

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            {$action}User(input: {
                id: 1
                name: "foo"
                tasks: {
                    delete: [1]
                }
            }) {
                id
                name
                tasks {
                    id
                    name
                }
            }
        }
GRAPHQL
        )->assertJson([
            'data' => [
                "{$action}User" => [
                    'id' => "{$user->id}",
                    'name' => 'foo',
                    'tasks' => [],
                ],
            ],
        ]);
    }

    /** @dataProvider existingModelMutations */
    public function testConnectHasMany(string $action): void
    {
        $user = factory(User::class)->create();
        assert($user instanceof User);

        $task1 = factory(Task::class)->create();
        assert($task1 instanceof Task);

        $task2 = factory(Task::class)->create();
        assert($task2 instanceof Task);

        $actionInputName = ucfirst($action);

        $this->graphQL(/** @lang GraphQL */ "
            mutation (\$input: {$actionInputName}UserInput!) {
                {$action}User(input: \$input) {
                    id
                    name
                    tasks {
                        id
                        name
                    }
                }
            }
            ",
            [
                'input' => [
                    'id' => $user->id,
                    'name' => 'foo',
                    'tasks' => [
                        'connect' => [
                            $task1->id,
                            $task2->id,
                        ],
                    ],
                ],
            ],
        )->assertJson([
            'data' => [
                "{$action}User" => [
                    'id' => "{$user->id}",
                    'name' => 'foo',
                    'tasks' => [
                        [
                            'id' => "{$task1->id}",
                            'name' => $task1->name,
                        ],
                        [
                            'id' => "{$task2->id}",
                            'name' => $task2->name,
                        ],
                    ],
                ],
            ],
        ]);
    }

    /** @dataProvider existingModelMutations */
    public function testDisconnectHasMany(string $action): void
    {
        $user = factory(User::class)->create();

        $taskDisconnect = factory(Task::class)->make();
        assert($taskDisconnect instanceof Task);
        $taskDisconnect->user()->associate($user);
        $taskDisconnect->save();

        $taskKeep = factory(Task::class)->make();
        assert($taskKeep instanceof Task);
        $taskKeep->user()->associate($user);
        $taskKeep->save();

        $actionInputName = ucfirst($action);

        $this->graphQL(/** @lang GraphQL */ "
            mutation (\$input: {$actionInputName}UserInput!) {
                {$action}User(input: \$input) {
                    id
                    name
                    tasks {
                        id
                        name
                    }
                }
            }
        ", [
            'input' => [
                'id' => $user->id,
                'name' => 'foo',
                'tasks' => [
                    'disconnect' => [
                        $taskDisconnect->id,
                    ],
                ],
            ],
        ])->assertJson([
            'data' => [
                "{$action}User" => [
                    'id' => "{$user->id}",
                    'name' => 'foo',
                    'tasks' => [
                        [
                            'id' => "{$taskKeep->id}",
                            'name' => $taskKeep->name,
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertNull($taskDisconnect->refresh()->user);
    }

    public function testUpsertAcrossPivotTableOverrideExistingModel(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String!
            roles: [Role!] @belongsToMany
        }

        type Role {
            id: ID!
            name: String!
        }

        type Mutation {
            upsertUser(input: UpsertUserInput! @spread): User @upsert
        }

        input UpsertUserInput {
            id: ID
            name: String!
            roles: UpsertRolesHasMany
        }

        input UpsertRolesHasMany {
            upsert: [UpsertRoleInput!]
        }

        input UpsertRoleInput {
            id: ID
            name: String!
            users: UpsertRoleUsersRelation
        }

        input UpsertRoleUsersRelation {
            sync: [ID!]
        }
        ' . self::PLACEHOLDER_QUERY;

        // Create the first User with a Role.
        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            upsertUser(input: {
                name: "foo"
                roles: {
                    upsert: [{
                        name: "bar"
                    }]
                }
            }) {
                id
                name
                roles {
                    id
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'upsertUser' => [
                    'id' => '1',
                    'name' => 'foo',
                    'roles' => [
                        [
                            'id' => '1',
                            'name' => 'bar',
                        ],
                    ],
                ],
            ],
        ]);

        // The first User has the first Role.
        $role = Role::firstOrFail();
        assert($role instanceof Role);
        $this->assertSame([1], $role->users()->pluck('users.id')->toArray());

        // Create another User.
        factory(User::class)->create();

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            upsertUser(input: {
                id: "1"
                name: "fooz"
                roles: {
                    upsert: [{
                        id: "1"
                        name: "baz"
                        users: {
                            sync: ["2"] # Here the first User is switching the relationship of the first Role to another User.
                        }
                    }]
                }
            }) {
                id
                name
                roles {
                    id
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'upsertUser' => [
                    'id' => '1',
                    'name' => 'fooz',
                    'roles' => [],
                ],
            ],
        ]);

        $this->assertSame([2], $role->users()->pluck('users.id')->toArray());
    }

    public function testConnectModelWithCustomKey(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            user: User @first
        }

        type CustomPrimaryKey {
            custom_primary_key_id: ID!
            users: [User!] @belongsTo
        }

        type User {
            id: ID!
            name: String
            customPrimaryKeys: [CustomPrimaryKey!] @hasMany
        }

        type Mutation {
            createUser(input: CreateUserInput! @spread): User @create
            updateUser(input: UpdateUserInput! @spread): User @update
        }

        input CreateUserInput {
            name: String
            customPrimaryKeys: UpdateCustomPrimaryKeyHasMany
        }

        input UpdateUserInput {
            id: ID!
            name: String
            customPrimaryKeys: UpdateCustomPrimaryKeyHasMany
        }

        input UpdateCustomPrimaryKeyHasMany {
            connect: [ID!]
            disconnect: [ID!]
        }
        ';

        factory(CustomPrimaryKey::class, 3)->create();

        $this->graphQL(/** @lang GraphQL */ '
            mutation {
                createUser(input: {
                    name: "foo"
                    customPrimaryKeys: {
                        connect: [1, 2, 3]
                    }
                }) {
                    id
                    name
                    customPrimaryKeys {
                        custom_primary_key_id
                    }
                }
            }
        ')->assertJson([
            'data' => [
                'createUser' => [
                    'id' => '1',
                    'name' => 'foo',
                    'customPrimaryKeys' => [
                        [
                            'custom_primary_key_id' => '1',
                        ],
                        [
                            'custom_primary_key_id' => '2',
                        ],
                        [
                            'custom_primary_key_id' => '3',
                        ],
                    ],
                ],
            ],
        ]);

        $this->graphQL(/** @lang GraphQL */ '
            mutation {
                updateUser(input: {
                    id: "1"
                    name: "bar"
                    customPrimaryKeys: {
                        disconnect: [1, 2]
                    }
                }) {
                    id
                    name
                    customPrimaryKeys {
                        custom_primary_key_id
                    }
                }
            }
        ')->assertJson([
            'data' => [
                'updateUser' => [
                    'id' => '1',
                    'name' => 'bar',
                    'customPrimaryKeys' => [
                        [
                            'custom_primary_key_id' => '3',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testUpdateNestedHasMany(): void
    {
        $user = factory(User::class)->create();
        assert($user instanceof User);

        $task = factory(Task::class)->create();
        assert($task instanceof Task);

        $this->graphQL(/** @lang GraphQL */ '
            mutation ($input: UpdateUserInput!) {
                updateUser(input: $input) {
                    id
                    name
                    tasks {
                        id
                        name
                    }
                }
            }
           ', [
            'input' => [
                'id' => $user->id,
                'name' => 'foo',
                'tasks' => [
                    'update' => [
                        [
                            'id' => $task->id,
                            'name' => 'bar',
                        ],
                    ],
                ],
            ],
        ])->assertJson([
            'data' => [
                'updateUser' => [
                    'id' => $user->id,
                    'name' => 'foo',
                    'tasks' => [
                        [
                            'id' => $task->id,
                            'name' => 'bar',
                        ],
                    ],
                ],
            ],
        ]);
    }
}
