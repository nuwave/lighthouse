<?php

namespace Tests\Integration\Execution\MutationExecutor;

use Faker\Provider\Lorem;
use Tests\DBTestCase;
use Tests\Utils\Models\Role;
use Tests\Utils\Models\User;

class BelongsToManyTest extends DBTestCase
{
    protected $schema = /** @lang GraphQL */ '
    type Role {
        id: ID!
        name: String
        users: [User!] @belongsToMany
        pivot: UserRolePivot
    }

    type User {
        id: ID!
        name: String
        roles: [Role!] @belongsToMany
    }

    type UserRolePivot {
        meta: String
    }

    type Mutation {
        createRole(input: CreateRoleInput! @spread): Role @create
        updateRole(input: UpdateRoleInput! @spread): Role @update
        upsertRole(input: UpsertRoleInput! @spread): Role @upsert
        createUser(input: CreateUserInput! @spread): User @create
        pivotsUpdateUser(input: UpsertUserInput! @spread): User @update
    }

    input CreateRoleInput {
        name: String
        users: CreateUserRelation
    }

    input CreateUserRelation {
        create: [CreateUserInput!]
        upsert: [UpsertUserInput!]
        connect: [ID!]
        sync: [ID!]
    }

    input CreateUserInput {
        name: String
    }

    input UpdateRoleInput {
        id: ID!
        name: String
        users: UpdateUserRelation
    }

    input UpdateUserRelation {
        create: [CreateUserInput!]
        update: [UpdateUserInput!]
        upsert: [UpsertUserInput!]
        delete: [ID!]
        connect: [ID!]
        sync: [ID!]
        syncWithoutDetaching: [ID!]
        disconnect: [ID!]
    }

    input UpdateUserInput {
        id: ID!
        name: String
    }

    input UpsertRoleInput {
        id: ID
        name: String
        users: UpsertUserRelation
    }

    input UpsertUserRelation {
        create: [CreateUserInput!]
        update: [UpdateUserInput!]
        upsert: [UpsertUserInput!]
        delete: [ID!]
        connect: [ID!]
        sync: [ID!]
        disconnect: [ID!]
    }

    input UpsertUserInput {
        id: ID
        name: String
        roles: UpdateRoleRelation
    }

    input UpdateRoleRelation {
        sync: [UpdateUserRolePivot!]
        syncWithoutDetaching: [UpdateUserRolePivot!]
        connect: [UpdateUserRolePivot!]
    }

    input UpdateUserRolePivot {
        id: ID! # role ID
        meta: String
    }
    '.self::PLACEHOLDER_QUERY;

    public function testSyncWithoutDetaching(): void
    {
        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            createUser(input: {
                name: "user1"
            }) {
                id
            }
            createRole(input: {
                name: "foobar"
                users: {
                    create: [
                        {
                            name: "user2"
                        }
                    ]
                }
            }) {
                id
                users {
                    id
                }
            }
            updateRole(input: {
                id: 1
                users: {
                    syncWithoutDetaching: [1]
                }
            }) {
                id
                users {
                    id
                }
            }
        }
        ')->assertJson([
            'data' => [
                'createUser' => [
                    'id' => '1',
                ],
                'createRole' => [
                    'id' => '1',
                    'users' => [
                        [
                            'id' => '2',
                        ],
                    ],
                ],
                'updateRole' => [
                    'id' => '1',
                    'users' => [
                        [
                            'id' => '2',
                        ],
                        [
                            'id' => '1',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testCreateWithNewBelongsToMany(): void
    {
        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            createRole(input: {
                name: "foobar"
                users: {
                    create: [
                        {
                            name: "bar"
                        },
                        {
                            name: "foo"
                        }
                    ]
                }
            }) {
                id
                name
                users {
                    id
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'createRole' => [
                    'id' => '1',
                    'name' => 'foobar',
                    'users' => [
                        [
                            'id' => '1',
                            'name' => 'bar',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testUpsertWithBelongsToManyOnNonExistentData(): void
    {
        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            upsertRole(input: {
                id: 1
                name: "is_user"
                users: {
                    upsert: [
                        {
                            id: 10
                            name: "user1"
                        },
                        {
                            id: 20
                            name: "user2"
                        }
                    ]
                }
            }) {
                id
                name
                users {
                    id
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'upsertRole' => [
                    'id' => '1',
                    'name' => 'is_user',
                    'users' => [
                        [
                            'id' => '10',
                            'name' => 'user1',
                        ],
                        [
                            'id' => '20',
                            'name' => 'user2',
                        ],
                    ],
                ],
            ],
        ]);

        /** @var Role $role */
        $role = Role::first();
        $this->assertCount(2, $role->users()->get());
        $this->assertSame('is_user', $role->name);
    }

    public function testUpsertBelongsToManyWithoutId(): void
    {
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            upsertRole(input: {
                name: "is_user"
                users: {
                    upsert: [
                        {
                            name: "user1"
                        },
                        {
                            name: "user2"
                        }
                    ]
                }
            }) {
                id
                name
                users {
                    id
                    name
                }
            }
        }
GRAPHQL
        )->assertJson([
            'data' => [
                'upsertRole' => [
                    'id' => '1',
                    'name' => 'is_user',
                    'users' => [
                        [
                            'id' => '1',
                            'name' => 'user1',
                        ],
                        [
                            'id' => '2',
                            'name' => 'user2',
                        ],
                    ],
                ],
            ],
        ]);

        /** @var Role $role */
        $role = Role::first();
        $this->assertCount(2, $role->users()->get());
        $this->assertSame('is_user', $role->name);
    }

    public function testCreateAndConnectWithBelongsToMany(): void
    {
        factory(User::class)->create(['name' => 'user_one']);
        factory(User::class)->create(['name' => 'user_two']);

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            createRole(input: {
                name: "foobar"
                users: {
                    connect: [1, 2]
                }
            }) {
                id
                name
                users {
                    id
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'createRole' => [
                    'id' => '1',
                    'name' => 'foobar',
                    'users' => [
                        [
                            'id' => '1',
                            'name' => 'user_one',
                        ],
                        [
                            'id' => '2',
                            'name' => 'user_two',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testUpsertUsingCreationAndConnectWithBelongsToMany(): void
    {
        factory(User::class)->create(['name' => 'user_one']);
        factory(User::class)->create(['name' => 'user_two']);

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            upsertRole(input: {
                id: 1
                name: "foobar"
                users: {
                    connect: [1, 2]
                }
            }) {
                id
                name
                users {
                    id
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'upsertRole' => [
                    'id' => '1',
                    'name' => 'foobar',
                    'users' => [
                        [
                            'id' => '1',
                            'name' => 'user_one',
                        ],
                        [
                            'id' => '2',
                            'name' => 'user_two',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testCreateWithBelongsToMany(): void
    {
        factory(Role::class)->create([
            'name' => 'is_admin',
        ]);

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            updateRole(input: {
                id: 1
                name: "is_user"
                users: {
                    create: [
                        {
                            name: "user1"
                        },
                        {
                            name: "user2"
                        }
                    ]
                }
            }) {
                id
                name
                users {
                    id
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'updateRole' => [
                    'id' => '1',
                    'name' => 'is_user',
                    'users' => [
                        [
                            'id' => '1',
                            'name' => 'user1',
                        ],
                        [
                            'id' => '2',
                            'name' => 'user2',
                        ],
                    ],
                ],
            ],
        ]);

        /** @var \Tests\Utils\Models\Role $role */
        $role = Role::first();
        $this->assertCount(2, $role->users()->get());
        $this->assertSame('is_user', $role->name);
    }

    public function testAllowsNullOperations(): void
    {
        factory(Role::class)->create();

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            updateRole(input: {
                id: 1
                name: "is_user"
                users: {
                    create: null
                    update: null
                    upsert: null
                    delete: null
                    connect: null
                    sync: null
                    syncWithoutDetaching: null
                    disconnect: null
                }
            }) {
                id
                name
                users {
                    id
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'updateRole' => [
                    'id' => '1',
                    'name' => 'is_user',
                    'users' => [],
                ],
            ],
        ]);
    }

    public function testUpsertUsingCreationWithBelongsToMany(): void
    {
        factory(Role::class)->create([
            'name' => 'is_admin',
        ]);

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            updateRole(input: {
                id: 1
                name: "is_user"
                users: {
                    upsert: [
                        {
                            id: 1
                            name: "user1"
                        },
                        {
                            id: 2
                            name: "user2"
                        }
                    ]
                }
            }) {
                id
                name
                users {
                    id
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'updateRole' => [
                    'id' => '1',
                    'name' => 'is_user',
                    'users' => [
                        [
                            'id' => '1',
                            'name' => 'user1',
                        ],
                        [
                            'id' => '2',
                            'name' => 'user2',
                        ],
                    ],
                ],
            ],
        ]);

        /** @var \Tests\Utils\Models\Role $role */
        $role = Role::first();
        $this->assertCount(2, $role->users()->get());
        $this->assertSame('is_user', $role->name);
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
    public function testUpdateWithBelongsToMany(string $action): void
    {
        factory(Role::class)
            ->create([
                'name' => 'is_admin',
            ])
            ->users()
            ->attach(
                factory(User::class, 2)->create()
            );

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            ${action}Role(input: {
                id: 1
                name: "is_user"
                users: {
                    ${action}: [{
                        id: 1
                        name: "user1"
                    },
                    {
                        id: 2
                        name: "user2"
                    }]
                }
            }) {
                id
                name
                users {
                    id
                    name
                }
            }
        }
GRAPHQL
        )->assertJson([
            'data' => [
                "${action}Role" => [
                    'id' => '1',
                    'name' => 'is_user',
                    'users' => [
                        [
                            'id' => '1',
                            'name' => 'user1',
                        ],
                        [
                            'id' => '2',
                            'name' => 'user2',
                        ],
                    ],
                ],
            ],
        ]);

        /** @var Role $role */
        $role = Role::first();
        $this->assertCount(2, $role->users()->get());
        $this->assertSame('is_user', $role->name);
    }

    /**
     * @dataProvider existingModelMutations
     */
    public function testDeleteWithBelongsToMany(string $action): void
    {
        factory(Role::class)
            ->create([
                'name' => 'is_admin',
            ])
            ->users()
            ->attach(
                factory(User::class, 2)->create()
            );

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            ${action}Role(input: {
                id: 1
                name: "is_user"
                users: {
                    delete: [1]
                }
            }) {
                id
                name
                users {
                    id
                }
            }
        }
GRAPHQL
        )->assertJson([
            'data' => [
                "${action}Role" => [
                    'id' => '1',
                    'name' => 'is_user',
                    'users' => [
                        [
                            'id' => '2',
                        ],
                    ],
                ],
            ],
        ]);

        /** @var Role $role */
        $role = Role::firstOrFail();
        $this->assertCount(1, $role->users()->get());
        $this->assertSame('is_user', $role->name);

        $this->assertNull(User::find(1));
        $this->assertNotNull(User::find(2));
    }

    /**
     * @dataProvider existingModelMutations
     */
    public function testConnectWithBelongsToMany(string $action): void
    {
        factory(User::class)->create();
        factory(Role::class)
            ->create()
            ->users()
            ->attach(
                factory(User::class)->create()
            );

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            ${action}Role(input: {
                id: 1
                users: {
                    connect: [1]
                }
            }) {
                id
                name
                users {
                    id
                }
            }
        }
GRAPHQL
        )->assertJson([
            'data' => [
                "${action}Role" => [
                    'id' => '1',
                    'users' => [
                        [
                            'id' => '2',
                        ],
                        [
                            'id' => '1',
                        ],
                    ],
                ],
            ],
        ]);

        /** @var Role $role */
        $role = Role::firstOrFail();
        $this->assertCount(2, $role->users()->get());
    }

    /**
     * @dataProvider existingModelMutations
     */
    public function testSyncWithBelongsToMany(string $action): void
    {
        factory(User::class)->create();
        factory(Role::class)
            ->create()
            ->users()
            ->attach(
                factory(User::class)->create()
            );

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            ${action}Role(input: {
                id: 1
                users: {
                    sync: [1, 2]
                }
            }) {
                id
                name
                users {
                    id
                }
            }
        }
GRAPHQL
        )->assertJson([
            'data' => [
                "${action}Role" => [
                    'id' => '1',
                    'users' => [
                        [
                            'id' => '2',
                        ],
                        [
                            'id' => '1',
                        ],
                    ],
                ],
            ],
        ]);

        /** @var Role $role */
        $role = Role::firstOrFail();
        $this->assertCount(2, $role->users()->get());
    }

    /**
     * @dataProvider existingModelMutations
     */
    public function testDisconnectWithBelongsToMany(string $action): void
    {
        factory(Role::class)
            ->create()
            ->users()
            ->attach(
                factory(User::class, 2)->create()
            );

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            ${action}Role(input: {
                id: 1
                users: {
                    disconnect: [1]
                }
            }) {
                id
                users {
                    id
                }
            }
        }
GRAPHQL
        )->assertJson([
            'data' => [
                "${action}Role" => [
                    'id' => '1',
                    'users' => [
                        [
                            'id' => '2',
                        ],
                    ],
                ],
            ],
        ]);

        /** @var Role $role */
        $role = Role::firstOrFail();
        $this->assertCount(1, $role->users()->get());

        $this->assertNotNull(User::find(1));
        $this->assertNotNull(User::find(2));
    }

    public function testSyncExistingUsersDuringCreateToABelongsToManyRelation(): void
    {
        factory(User::class, 2)->create();

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            createRole(input: {
                name: "foobar"
                users: {
                    sync: [1, 2]
                }
            }) {
                id
                name
                users {
                    id
                }
            }
        }
        ')->assertJson([
            'data' => [
                'createRole' => [
                    'id' => '1',
                    'name' => 'foobar',
                    'users' => [
                        [
                            'id' => '1',
                        ],
                        [
                            'id' => '2',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testSyncExistingUsersDuringCreateUsingUpsertToABelongsToManyRelation(): void
    {
        factory(User::class, 2)->create();

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            upsertRole(input: {
                id: 1
                name: "foobar"
                users: {
                    sync: [1, 2]
                }
            }) {
                id
                name
                users {
                    id
                }
            }
        }
        ')->assertJson([
            'data' => [
                'upsertRole' => [
                    'id' => '1',
                    'name' => 'foobar',
                    'users' => [
                        [
                            'id' => '1',
                        ],
                        [
                            'id' => '2',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @dataProvider existingModelMutations
     */
    public function testDisconnectAllRelatedModelsOnEmptySync(string $action): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();
        /** @var Role $role */
        $role = $user->roles()->save(
            factory(Role::class)->make()
        );

        $this->assertCount(1, $role->users);

        $this->graphQL(/** @lang GraphQL */ "
        mutation {
            ${action}Role(input: {
                id: 1
                users: {
                    sync: []
                }
            }) {
                id
                name
                users {
                    id
                }
            }
        }
        ")->assertJson([
            'data' => [
                "${action}Role" => [
                    'id' => '1',
                    'users' => [],
                ],
            ],
        ]);

        $role->refresh();

        $this->assertCount(0, $role->users);
    }

    public function testConnectUserWithRoleAndPivotMetaByUsingSync(): void
    {
        $user = factory(User::class)->create();
        factory(Role::class)->create();
        $role2 = factory(Role::class)->create();
        $user->roles()->attach($role2);

        $meta = Lorem::sentence();

        $this->graphQL(/** @lang GraphQL */ '
        mutation ($meta: String) {
            pivotsUpdateUser(input: {
                id: 1,
                roles: {
                    sync: [
                        {
                            id: 1,
                            meta: $meta
                        },
                        {
                            id: 2
                        }
                    ]
                },
            }) {
                roles {
                    id
                    pivot {
                        meta
                    }
                }
            }
        }
        ', [
            'meta' => $meta,
        ])->assertJson([
            'data' => [
                'pivotsUpdateUser' => [
                    'roles' => [
                        [
                            'id' => 2,
                            'pivot' => [
                                'meta' => null,
                            ],
                        ],
                        [
                            'id' => 1,
                            'pivot' => [
                                'meta' => $meta,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testConnectUserWithRoleAndPivotMetaByUsingSyncWithoutDetach(): void
    {
        $user = factory(User::class)->create();
        factory(Role::class)->create();
        $role2 = factory(Role::class)->create();
        $user->roles()->attach($role2);

        $meta = Lorem::sentence();

        $this->graphQL(/** @lang GraphQL */ '
        mutation ($meta: String) {
            pivotsUpdateUser(input: {
                id: 1,
                roles: {
                    syncWithoutDetaching: [
                        {
                            id: 1,
                            meta: $meta
                        }
                    ]
                },
            }) {
                roles {
                    pivot {
                        meta
                    }
                }
            }
        }
        ', [
            'meta' => $meta,
        ])->assertJson([
            'data' => [
                'pivotsUpdateUser' => [
                    'roles' => [
                        [
                            'pivot' => [
                                'meta' => null,
                            ],
                        ],
                        [
                            'pivot' => [
                                'meta' => $meta,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testConnectUserWithRoleAndPivotMetaByUsingConnect(): void
    {
        factory(User::class)->create();
        factory(Role::class)->create();

        $meta = Lorem::sentence();

        $this->graphQL(/** @lang GraphQL */ '
        mutation ($meta: String) {
            pivotsUpdateUser(input: {
                id: 1,
                roles: {
                    connect: [
                        {
                            id: 1,
                            meta: $meta
                        }
                    ]
                },
            }) {
                roles {
                    pivot {
                        meta
                    }
                }
            }
        }
        ', [
            'meta' => $meta,
        ])->assertJson([
            'data' => [
                'pivotsUpdateUser' => [
                    'roles' => [
                        [
                            'pivot' => [
                                'meta' => $meta,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
