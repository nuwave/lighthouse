<?php declare(strict_types=1);

namespace Tests\Integration\Execution\MutationExecutor;

use Nuwave\Lighthouse\Execution\Arguments\ParentRelationScope;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\DBTestCase;
use Tests\Utils\Models\CustomPrimaryKey;
use Tests\Utils\Models\Role;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

final class HasManyTest extends DBTestCase
{
    protected string $schema = /** @lang GraphQL */ <<<'GRAPHQL'
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
    GRAPHQL . self::PLACEHOLDER_QUERY;

    public function testCreateWithNewHasMany(): void
    {
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL)->assertJson([
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
        $this->assertInstanceOf(Task::class, $task1);

        $task2 = factory(Task::class)->create();
        $this->assertInstanceOf(Task::class, $task2);

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            mutation ($input: CreateUserInput!) {
                createUser(input: $input) {
                    name
                    tasks {
                        id
                        name
                    }
                }
            }
        GRAPHQL,
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

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL)->assertJson([
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
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL)->assertJson([
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
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL)->assertJson([
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
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL)->assertJson([
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
    #[DataProvider('existingModelMutations')]
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
    #[DataProvider('existingModelMutations')]
    public function testUpdateHasMany(string $action): void
    {
        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

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
    #[DataProvider('existingModelMutations')]
    public function testUpsertHasMany(string $action): void
    {
        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

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
        GRAPHQL,
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
    #[DataProvider('existingModelMutations')]
    public function testDeleteHasMany(string $action): void
    {
        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

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
        GRAPHQL,
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
    #[DataProvider('existingModelMutations')]
    public function testConnectHasMany(string $action): void
    {
        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $task1 = factory(Task::class)->create();
        $this->assertInstanceOf(Task::class, $task1);

        $task2 = factory(Task::class)->create();
        $this->assertInstanceOf(Task::class, $task2);

        $actionInputName = ucfirst($action);

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
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
        GRAPHQL,
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
    #[DataProvider('existingModelMutations')]
    public function testDisconnectHasMany(string $action): void
    {
        $user = factory(User::class)->create();

        $taskDisconnect = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $taskDisconnect);
        $taskDisconnect->user()->associate($user);
        $taskDisconnect->save();

        $taskKeep = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $taskKeep);
        $taskKeep->user()->associate($user);
        $taskKeep->save();

        $actionInputName = ucfirst($action);

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
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
        GRAPHQL, [
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
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL . self::PLACEHOLDER_QUERY;

        // Create the first User with a Role.
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL)->assertJson([
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
        $role = Role::query()->firstOrFail();
        $this->assertSame([1], $role->users()->pluck('users.id')->toArray());

        // Create another User.
        factory(User::class)->create();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL)->assertJson([
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
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL;

        factory(CustomPrimaryKey::class, 3)->create();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL)->assertJson([
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

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL)->assertJson([
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
        $this->assertInstanceOf(User::class, $user);

        $task = factory(Task::class)->create();
        $this->assertInstanceOf(Task::class, $task);

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL, [
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

    public function testDeleteHasManyWithoutRelationFailsWhenScopedToParent(): void
    {
        config(['lighthouse.nested_mutations_scope_to_parent' => true]);

        $user = factory(User::class)->create();
        $otherUsersTask = factory(Task::class)->create();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation ($userID: ID!, $taskID: ID!) {
            updateUser(input: {
                id: $userID
                tasks: {
                    delete: [$taskID]
                }
            }) {
                id
            }
        }
        GRAPHQL, [
            'userID' => $user->id,
            'taskID' => $otherUsersTask->id,
        ])->assertGraphQLErrorMessage(ParentRelationScope::unrelatedModel([$otherUsersTask->id]));

        $this->assertNotNull(Task::find($otherUsersTask->id));
    }

    public function testDeleteHasManyWithMissingIDFailsWhenScopedToParent(): void
    {
        config(['lighthouse.nested_mutations_scope_to_parent' => true]);

        $user = factory(User::class)->create();
        $task = factory(Task::class)->make();
        $task->user()->associate($user);
        $task->save();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation ($userID: ID!, $taskID: ID!) {
            updateUser(input: {
                id: $userID
                tasks: {
                    delete: [$taskID, 999]
                }
            }) {
                id
            }
        }
        GRAPHQL, [
            'userID' => $user->id,
            'taskID' => $task->id,
        ])->assertGraphQLErrorMessage('Cannot modify a model that is not related to the given parent: 999.');

        $this->assertNotNull(Task::find($task->id));
    }

    public function testDeleteHasManyWhenScopedToParent(): void
    {
        config(['lighthouse.nested_mutations_scope_to_parent' => true]);

        $user = factory(User::class)->create();
        $task = factory(Task::class)->make();
        $task->user()->associate($user);
        $task->save();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation ($userID: ID!, $taskID: ID!) {
            updateUser(input: {
                id: $userID
                tasks: {
                    delete: [$taskID]
                }
            }) {
                tasks {
                    id
                }
            }
        }
        GRAPHQL, [
            'userID' => $user->id,
            'taskID' => $task->id,
        ])->assertExactJson([
            'data' => [
                'updateUser' => [
                    'tasks' => [],
                ],
            ],
        ]);

        $this->assertNull(Task::find($task->id));
    }

    /** @return iterable<array{bool}> */
    public static function scopeToParent(): iterable
    {
        yield 'unscoped' => [false];
        yield 'scoped to parent' => [true];
    }

    /** @dataProvider scopeToParent */
    #[DataProvider('scopeToParent')]
    public function testDeleteHasManyWithSingleID(bool $scopeToParent): void
    {
        config(['lighthouse.nested_mutations_scope_to_parent' => $scopeToParent]);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Task {
            id: ID!
        }

        type User {
            id: ID!
            tasks: [Task!]! @hasMany
        }

        type Mutation {
            updateUser(input: UpdateUserInput! @spread): User @update
        }

        input UpdateUserInput {
            id: ID!
            tasks: DeleteTaskRelation
        }

        input DeleteTaskRelation {
            delete: ID
        }
        GRAPHQL . self::PLACEHOLDER_QUERY;

        $user = factory(User::class)->create();
        $task = factory(Task::class)->make();
        $task->user()->associate($user);
        $task->save();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation ($userID: ID!, $taskID: ID!) {
            updateUser(input: {
                id: $userID
                tasks: {
                    delete: $taskID
                }
            }) {
                tasks {
                    id
                }
            }
        }
        GRAPHQL, [
            'userID' => $user->id,
            'taskID' => $task->id,
        ])->assertExactJson([
            'data' => [
                'updateUser' => [
                    'tasks' => [],
                ],
            ],
        ]);

        $this->assertNull(Task::find($task->id));
    }

    public function testDisconnectHasManyWithoutRelationFailsWhenScopedToParent(): void
    {
        config(['lighthouse.nested_mutations_scope_to_parent' => true]);

        $user = factory(User::class)->create();
        $otherUser = factory(User::class)->create();
        $otherUsersTask = factory(Task::class)->make();
        $otherUsersTask->user()->associate($otherUser);
        $otherUsersTask->save();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation ($userID: ID!, $taskID: ID!) {
            updateUser(input: {
                id: $userID
                tasks: {
                    disconnect: [$taskID]
                }
            }) {
                id
            }
        }
        GRAPHQL, [
            'userID' => $user->id,
            'taskID' => $otherUsersTask->id,
        ])->assertGraphQLErrorMessage(ParentRelationScope::unrelatedModel([$otherUsersTask->id]));

        $otherUsersTask->refresh();

        $this->assertSame($otherUser->id, $otherUsersTask->user_id);
    }

    public function testDisconnectHasManyWithoutRelationDetachesFromOtherParent(): void
    {
        $user = factory(User::class)->create();
        $otherUser = factory(User::class)->create();
        $otherUsersTask = factory(Task::class)->make();
        $otherUsersTask->user()->associate($otherUser);
        $otherUsersTask->save();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation ($userID: ID!, $taskID: ID!) {
            updateUser(input: {
                id: $userID
                tasks: {
                    disconnect: [$taskID]
                }
            }) {
                id
            }
        }
        GRAPHQL, [
            'userID' => $user->id,
            'taskID' => $otherUsersTask->id,
        ])->assertGraphQLErrorFree();

        $otherUsersTask->refresh();

        $this->assertNull($otherUsersTask->user_id);
    }

    public function testUpdateHasManyWithoutRelationFailsWhenScopedToParent(): void
    {
        config(['lighthouse.nested_mutations_scope_to_parent' => true]);

        $user = factory(User::class)->create();
        $otherUser = factory(User::class)->create();
        $otherUsersTask = factory(Task::class)->make();
        $otherUsersTask->name = 'original';
        $otherUsersTask->user()->associate($otherUser);
        $otherUsersTask->save();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation ($userID: ID!, $taskID: ID!) {
            updateUser(input: {
                id: $userID
                tasks: {
                    update: [{ id: $taskID, name: "hacked" }]
                }
            }) {
                id
            }
        }
        GRAPHQL, [
            'userID' => $user->id,
            'taskID' => $otherUsersTask->id,
        ])->assertGraphQLErrorMessage(ParentRelationScope::unrelatedModel([$otherUsersTask->id]));

        $otherUsersTask->refresh();
        $this->assertSame('original', $otherUsersTask->name);
        $this->assertSame($otherUser->id, $otherUsersTask->user_id);
    }

    public function testUpsertHasManyWithoutRelationFailsWhenScopedToParent(): void
    {
        config(['lighthouse.nested_mutations_scope_to_parent' => true]);

        $user = factory(User::class)->create();
        $otherUser = factory(User::class)->create();
        $otherUsersTask = factory(Task::class)->make();
        $otherUsersTask->name = 'original';
        $otherUsersTask->user()->associate($otherUser);
        $otherUsersTask->save();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation ($userID: ID!, $taskID: ID!) {
            updateUser(input: {
                id: $userID
                tasks: {
                    upsert: [{ id: $taskID, name: "hacked" }]
                }
            }) {
                id
            }
        }
        GRAPHQL, [
            'userID' => $user->id,
            'taskID' => $otherUsersTask->id,
        ])->assertGraphQLErrorMessage(ParentRelationScope::unrelatedModel([$otherUsersTask->id]));

        $otherUsersTask->refresh();
        $this->assertSame('original', $otherUsersTask->name);
        $this->assertSame($otherUser->id, $otherUsersTask->user_id);
    }

    public function testUpsertHasManyWithMissingIDCreatesWhenScopedToParent(): void
    {
        config(['lighthouse.nested_mutations_scope_to_parent' => true]);

        $user = factory(User::class)->create();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation ($userID: ID!) {
            updateUser(input: {
                id: $userID
                tasks: {
                    upsert: [{ id: 999, name: "new" }]
                }
            }) {
                tasks {
                    id
                    name
                }
            }
        }
        GRAPHQL, [
            'userID' => $user->id,
        ])->assertExactJson([
            'data' => [
                'updateUser' => [
                    'tasks' => [
                        [
                            'id' => '999',
                            'name' => 'new',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testUpdateHasManyWhenScopedToParent(): void
    {
        config(['lighthouse.nested_mutations_scope_to_parent' => true]);

        $user = factory(User::class)->create();
        $task = factory(Task::class)->make();
        $task->user()->associate($user);
        $task->save();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation ($userID: ID!, $taskID: ID!) {
            updateUser(input: {
                id: $userID
                tasks: {
                    update: [{ id: $taskID, name: "updated" }]
                }
            }) {
                tasks {
                    name
                }
            }
        }
        GRAPHQL, [
            'userID' => $user->id,
            'taskID' => $task->id,
        ])->assertExactJson([
            'data' => [
                'updateUser' => [
                    'tasks' => [
                        [
                            'name' => 'updated',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testUpsertHasManyWhenScopedToParent(): void
    {
        config(['lighthouse.nested_mutations_scope_to_parent' => true]);

        $user = factory(User::class)->create();
        $task = factory(Task::class)->make();
        $task->user()->associate($user);
        $task->save();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation ($userID: ID!, $taskID: ID!) {
            updateUser(input: {
                id: $userID
                tasks: {
                    upsert: [{ id: $taskID, name: "upserted" }]
                }
            }) {
                tasks {
                    name
                }
            }
        }
        GRAPHQL, [
            'userID' => $user->id,
            'taskID' => $task->id,
        ])->assertExactJson([
            'data' => [
                'updateUser' => [
                    'tasks' => [
                        [
                            'name' => 'upserted',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame(1, Task::count());
    }

    public function testDisconnectHasManyWhenScopedToParent(): void
    {
        config(['lighthouse.nested_mutations_scope_to_parent' => true]);

        $user = factory(User::class)->create();
        $task = factory(Task::class)->make();
        $task->user()->associate($user);
        $task->save();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation ($userID: ID!, $taskID: ID!) {
            updateUser(input: {
                id: $userID
                tasks: {
                    disconnect: [$taskID]
                }
            }) {
                tasks {
                    id
                }
            }
        }
        GRAPHQL, [
            'userID' => $user->id,
            'taskID' => $task->id,
        ])->assertExactJson([
            'data' => [
                'updateUser' => [
                    'tasks' => [],
                ],
            ],
        ]);

        $task->refresh();
        $this->assertNull($task->user_id);
    }

    public function testDisconnectHasManyWithOwnAndForeignIDsFailsWhenScopedToParent(): void
    {
        config(['lighthouse.nested_mutations_scope_to_parent' => true]);

        $user = factory(User::class)->create();
        $task = factory(Task::class)->make();
        $task->user()->associate($user);
        $task->save();
        $otherUsersTask = factory(Task::class)->create();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation ($userID: ID!, $taskIDs: [ID!]!) {
            updateUser(input: {
                id: $userID
                tasks: {
                    disconnect: $taskIDs
                }
            }) {
                id
            }
        }
        GRAPHQL, [
            'userID' => $user->id,
            'taskIDs' => [$task->id, $otherUsersTask->id],
        ])->assertGraphQLErrorMessage(ParentRelationScope::unrelatedModel([$otherUsersTask->id]));

        $task->refresh();
        $this->assertSame($user->id, $task->user_id);
    }
}
