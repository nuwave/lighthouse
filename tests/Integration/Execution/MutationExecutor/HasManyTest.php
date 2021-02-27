<?php

namespace Tests\Integration\Execution\MutationExecutor;

use Tests\DBTestCase;
use Tests\Utils\Models\Role;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class HasManyTest extends DBTestCase
{
    protected $schema = /** @lang GraphQL */ '
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
    '.self::PLACEHOLDER_QUERY;

    public function testCanCreateWithNewHasMany(): void
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

    public function testCanCreateWithConnectHasMany(): void
    {
        $task1 = factory(Task::class)->create();
        $task2 = factory(Task::class)->create();

        $this->graphQL(/** @lang GraphQL */ '
            mutation ($input: CreateUserInput!) {
                createUser(input: $input) {
                    id
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
            ]
        )->assertJson([
            'data' => [
                'createUser' => [
                    'name' => 'foo',
                    'tasks' => [
                        [
                            'id' => $task1->id,
                            'name' => $task1->name,
                        ],
                        [
                            'id' => $task2->id,
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

    public function testCanUpsertWithNewHasMany(): void
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

    public function testCanCreateUsingUpsertWithNewHasMany(): void
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
    public function testCanCreateHasMany(string $action): void
    {
        factory(User::class)->create();

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            ${action}User(input: {
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
GRAPHQL
        )->assertJson([
            'data' => [
                "${action}User" => [
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

    /**
     * @dataProvider existingModelMutations
     */
    public function testCanUpdateHasMany(string $action): void
    {
        factory(User::class)
            ->create()
            ->tasks()
            ->save(
                factory(Task::class)->create()
            );

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            ${action}User(input: {
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
GRAPHQL
        )->assertJson([
            'data' => [
                "${action}User" => [
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

    /**
     * @dataProvider existingModelMutations
     */
    public function testCanUpsertHasMany(string $action): void
    {
        factory(User::class)
            ->create()
            ->tasks()
            ->save(
                factory(Task::class)->create()
            );

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            ${action}User(input: {
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
                "${action}User" => [
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

    /**
     * @dataProvider existingModelMutations
     */
    public function testCanDeleteHasMany(string $action): void
    {
        factory(User::class)
            ->create()
            ->tasks()
            ->save(
                factory(Task::class)->create()
            );

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            ${action}User(input: {
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
                "${action}User" => [
                    'id' => '1',
                    'name' => 'foo',
                    'tasks' => [],
                ],
            ],
        ]);
    }

    /**
     * @dataProvider existingModelMutations
     */
    public function testCanConnectHasMany(string $action): void
    {
        $user = factory(User::class)->create();
        $task1 = factory(Task::class)->create();
        $task2 = factory(Task::class)->create();

        $actionInputName = ucfirst($action);

        $this->graphQL(/** @lang GraphQL */ "
            mutation ${action}User(\$input: {$actionInputName}UserInput!){
                ${action}User(input: \$input) {
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
            ]
        )->assertJson([
            'data' => [
                "${action}User" => [
                    'id' => '1',
                    'name' => 'foo',
                    'tasks' => [
                        [
                            'id' => $task1->id,
                            'name' => $task1->name,
                        ],
                        [
                            'id' => $task2->id,
                            'name' => $task2->name,
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @dataProvider existingModelMutations
     */
    public function testCanDisconnectHasMany(string $action): void
    {
        $user = factory(User::class)->create();

        /** @var \Tests\Utils\Models\Task $taskDisconnect */
        $taskDisconnect = factory(Task::class)->make();
        $taskDisconnect->user()->associate($user);
        $taskDisconnect->save();

        /** @var \Tests\Utils\Models\Task $taskKeep */
        $taskKeep = factory(Task::class)->make();
        $taskKeep->user()->associate($user);
        $taskKeep->save();

        $actionInputName = ucfirst($action);

        $this->graphQL(/** @lang GraphQL */ "
            mutation ${action}User(\$input: {$actionInputName}UserInput!){
                ${action}User(input: \$input) {
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
                "${action}User" => [
                    'id' => '1',
                    'name' => 'foo',
                    'tasks' => [
                        [
                            'id' => $taskKeep->id,
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
        '.self::PLACEHOLDER_QUERY;

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
        $this->assertEquals([1], $role->users()->pluck('users.id')->toArray());

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

        $this->assertEquals([2], $role->users()->pluck('users.id')->toArray());
    }
}
