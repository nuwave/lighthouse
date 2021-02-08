<?php

namespace Tests\Integration\Execution\MutationExecutor;

use Tests\DBTestCase;
use Tests\Utils\Models\Role;
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

    public function testBelongsToExplicitNullHasNoEffect(): void
    {
        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            createTask(input: {
                name: "foo"
                user: null
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
                    'user' => null,
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

    public function testAllowsNullOperations(): void
    {
        factory(User::class)->create();

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            upsertTask(input: {
                id: 1
                name: "foo"
                user: {
                    connect: null
                    create: null
                    update: null
                    upsert: null
                    disconnect: null
                    delete: null
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

    public function testUpsertBelongsToWithoutId(): void
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

    /**
     * @return array<array<string, string>>
     */
    public function existingModelMutations(): array
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

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            ${action}Task(input: {
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
GRAPHQL
        )->assertJson([
            'data' => [
                "${action}Task" => [
                    'id' => '1',
                    'name' => 'foo',
                    'user' => null,
                ],
            ],
        ]);

        $this->assertNotNull(
            User::find(1),
            'Must not delete the second model.'
        );

        /** @var \Tests\Utils\Models\Task $task */
        $task = Task::findOrFail(1);
        $this->assertNull($task->user, 'Must disconnect the parent relationship.');
    }

    public function testCanCreateUsingUpsertAndDisconnectBelongsTo(): void
    {
        /** @var \Tests\Utils\Models\User $user */
        $user = factory(User::class)->create();
        /** @var \Tests\Utils\Models\Task $task */
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
            User::findOrFail($user->id)->exists,
            'Must not delete the second model.'
        );

        $task->refresh();
        $this->assertNull(
            $task->user_id,
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
        /** @var \Tests\Utils\Models\Task $task */
        $task = $user->tasks()->save(
            factory(Task::class)->make()
        );

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            ${action}Task(input: {
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
GRAPHQL
        )->assertJson([
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

        $task->refresh();
        $this->assertNull(
            $task->user_id,
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
            Task::findOrFail(1)->user,
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
        /** @var \Tests\Utils\Models\Task $task */
        $task = $user->tasks()->save(
            factory(Task::class)->make()
        );

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            ${action}Task(input: {
                id: 1
                name: "foo"
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
GRAPHQL
        )->assertJson([
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

        $task->refresh();

        $taskUser = $task->user;
        $this->assertNotNull($taskUser);

        $this->assertSame(
            $user->id,
            $taskUser->id,
            'The parent relationship remains untouched.'
        );
    }

    public function testUpsertAcrossTwoNestedBelongsToRelations(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            name: String!
            roles: [Role!] @belongsToMany
        }

        type Role {
            name: String!
        }

        type Mutation {
            upsertUser(input: UpsertUserInput! @spread): User @upsert
        }

        input UpsertUserInput {
            name: String!
            rolesPivot: UpsertRoleUserPivotBelongsTo
        }

        input UpsertRoleUserPivotBelongsTo {
            upsert: [UpsertRoleUserPivotInput!]
        }

        input UpsertRoleUserPivotInput {
            role: UpsertRoleBelongsTo
        }

        input UpsertRoleBelongsTo {
            upsert: UpsertRoleInput
        }

        input UpsertRoleInput {
            name: String!
        }
        '.self::PLACEHOLDER_QUERY;

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            upsertUser(input: {
                name: "foo"
                rolesPivot: {
                    upsert: [{
                        role: {
                            upsert: {
                                name: "bar"
                            }
                        }
                    }]
                }
            }) {
                name
                roles {
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'upsertUser' => [
                    'name' => 'foo',
                    'roles' => [
                        [
                            'name' => 'bar',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testUpsertAcrossTwoNestedBelongsToRelationsAndOverrideExistingModel(): void
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
            rolesPivot: UpsertRoleUserPivotBelongsTo
        }

        input UpsertRoleUserPivotBelongsTo {
            upsert: [UpsertRoleUserPivotInput!]
        }

        input UpsertRoleUserPivotInput {
            id: ID
            role: UpsertRoleBelongsTo
        }

        input UpsertRoleBelongsTo {
            upsert: UpsertRoleInput
        }

        input UpsertRoleInput {
            id: ID
            name: String!
            users: UpsertRoleUsersRelation
        }

        input UpsertRoleUsersRelation {
            sync: [ID!]
        }
        '.self::PLACEHOLDER_QUERY;

        // Create the first User with a Role.
        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            upsertUser(input: {
                name: "foo"
                rolesPivot: {
                    upsert: [{
                        role: {
                            upsert: {
                                name: "bar"
                            }
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
        $this->assertEquals([1], $role->users()->pluck('users.id')->toArray());

        // Create another User.
        factory(User::class)->create();

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            upsertUser(input: {
                id: "1"
                name: "fooz"
                rolesPivot: {
                    upsert: [{
                        id: "1"
                        role: {
                            upsert: {
                                id: "1"
                                name: "baz"
                                users: {
                                    sync: ["2"] # Here the first User is switching the relationship of the first Role to another User.
                                }
                            }
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

        $this->assertEquals([2], $role->users()->pluck('users.id')->toArray());
    }

    public function testCreateMultipleBelongsToThatDontExistYet(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type RoleUserPivot {
            id: ID!
            user: User!
            role: Role!
        }

        type User {
            id: ID!
            name: String!
        }

        type Role {
            id: ID!
            name: String!
        }

        type Mutation {
            createRoleUser(
                input: RoleUserInput! @spread
            ): RoleUserPivot @create
        }

        input RoleUserInput {
            id: ID!
            user: UserInput!
            role: RoleInput!
        }

        input UserInput {
            create: CreateUserInput
        }

        input CreateUserInput {
            id: ID!
            name: String!
        }

        input RoleInput {
            create: CreateRoleInput
        }

        input CreateRoleInput {
            id: ID!
            name: String!
        }
        '.self::PLACEHOLDER_QUERY;

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            createRoleUser(input: {
                id: "1"
                user: {
                    create: {
                        id: "2"
                        name: "user 1"
                    }
                }
                role: {
                    create: {
                        id: "3",
                        name: "role 1"
                    }
                }
            }) {
                id
                user {
                    id
                    name
                }
                role {
                    id
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'createRoleUser' => [
                    'id' => '1',
                    'user' => [
                        'id' => '2',
                        'name' => 'user 1',
                    ],
                    'role' => [
                        'id' => '3',
                        'name' => 'role 1',
                    ],
                ],
            ],
        ]);
    }

    public function testCreateMultipleBelongsToThatDontExistYetWithExistingRecords(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type RoleUserPivot {
            id: ID!
            meta: String
            user: User!
            role: Role!
        }

        type User {
            id: ID!
            name: String!
        }

        type Role {
            id: ID!
            name: String!
        }

        type Mutation {
            createRoleUser(
                input: RoleUserInput! @spread
            ): RoleUserPivot @create
        }

        input RoleUserInput {
            id: ID
            meta: String
            user: UserInput!
            role: RoleInput!
        }

        input UserInput {
            create: CreateUserInput
        }

        input CreateUserInput {
            id: ID
            name: String!
        }

        input RoleInput {
            create: CreateRoleInput
        }

        input CreateRoleInput {
            id: ID
            name: String!
        }
        '.self::PLACEHOLDER_QUERY;

        $query = /** @lang GraphQL */ '
        mutation {
            createRoleUser(input: {
                meta: "asdf"
                user: {
                    create: {
                        name: "some username"
                    }
                }
                role: {
                    create: {
                        name: "some rolename"
                    }
                }
            }) {
                id
                meta
                user {
                    id
                    name
                }
                role {
                    id
                    name
                }
            }
        }';

        // This must first create a user, then a role, then attach them to the pivot
        $this->graphQL($query)->assertJson([
            'data' => [
                'createRoleUser' => [
                    'id' => '1',
                    'meta' => 'asdf',
                    'user' => [
                        'id' => '1',
                        'name' => 'some username',
                    ],
                    'role' => [
                        'id' => '1',
                        'name' => 'some rolename',
                    ],
                ],
            ],
        ]);

        // We should be able to repeat this query and create new entries the same way
        $this->graphQL($query)->assertJson([
            'data' => [
                'createRoleUser' => [
                    'id' => '2',
                    'meta' => 'asdf',
                    'user' => [
                        'id' => '2',
                        'name' => 'some username',
                    ],
                    'role' => [
                        'id' => '2',
                        'name' => 'some rolename',
                    ],
                ],
            ],
        ]);
    }
}
