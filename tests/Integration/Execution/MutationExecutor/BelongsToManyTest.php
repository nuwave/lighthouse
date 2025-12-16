<?php declare(strict_types=1);

namespace Tests\Integration\Execution\MutationExecutor;

use Faker\Provider\Lorem;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\DBTestCase;
use Tests\Utils\Models\Role;
use Tests\Utils\Models\User;

final class BelongsToManyTest extends DBTestCase
{
    protected string $schema = /** @lang GraphQL */ '
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
    ' . self::PLACEHOLDER_QUERY;

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

        $role = Role::query()->firstOrFail();
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
        GRAPHQL)->assertJson([
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

        $role = Role::query()->firstOrFail();
        $this->assertCount(2, $role->users()->get());
        $this->assertSame('is_user', $role->name);
    }

    public function testCreateAndConnectWithBelongsToMany(): void
    {
        $user = factory(User::class)->make();
        $this->assertInstanceOf(User::class, $user);
        $user->name = 'user_one';
        $user->save();

        $user = factory(User::class)->make();
        $this->assertInstanceOf(User::class, $user);
        $user->name = 'user_two';
        $user->save();

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
        $user = factory(User::class)->make();
        $this->assertInstanceOf(User::class, $user);
        $user->name = 'user_one';
        $user->save();

        $user = factory(User::class)->make();
        $this->assertInstanceOf(User::class, $user);
        $user->name = 'user_two';
        $user->save();

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
        $role = factory(Role::class)->make();
        $this->assertInstanceOf(Role::class, $role);
        $role->name = 'is_admin';
        $role->save();

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

        $role = Role::query()->firstOrFail();
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
        $role = factory(Role::class)->make();
        $this->assertInstanceOf(Role::class, $role);
        $role->name = 'is_admin';
        $role->save();

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

        $role = Role::query()->firstOrFail();
        $this->assertCount(2, $role->users()->get());
        $this->assertSame('is_user', $role->name);
    }

    /** @return iterable<array{string}> */
    public static function existingModelMutations(): iterable
    {
        yield 'Update action' => ['update'];
        yield 'Upsert action' => ['upsert'];
    }

    /** @dataProvider existingModelMutations */
    #[DataProvider('existingModelMutations')]
    public function testUpdateWithBelongsToMany(string $action): void
    {
        $role = factory(Role::class)->make();
        $this->assertInstanceOf(Role::class, $role);
        $role->name = 'is_admin';
        $role->save();

        $role->users()
            ->attach(
                factory(User::class, 2)->create(),
            );

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            {$action}Role(input: {
                id: 1
                name: "is_user"
                users: {
                    {$action}: [{
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
        GRAPHQL)->assertJson([
            'data' => [
                "{$action}Role" => [
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

        $role = Role::query()->firstOrFail();
        $this->assertCount(2, $role->users()->get());
        $this->assertSame('is_user', $role->name);
    }

    /** @dataProvider existingModelMutations */
    #[DataProvider('existingModelMutations')]
    public function testDeleteWithBelongsToMany(string $action): void
    {
        $role = factory(Role::class)->make();
        $this->assertInstanceOf(Role::class, $role);
        $role->name = 'is_admin';
        $role->save();

        $role->users()
            ->attach(
                factory(User::class, 2)->create(),
            );

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            {$action}Role(input: {
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
        GRAPHQL)->assertJson([
            'data' => [
                "{$action}Role" => [
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

        $role = Role::query()->firstOrFail();
        $this->assertCount(1, $role->users()->get());
        $this->assertSame('is_user', $role->name);

        $this->assertNull(User::find(1));
        $this->assertNotNull(User::find(2));
    }

    /** @dataProvider existingModelMutations */
    #[DataProvider('existingModelMutations')]
    public function testConnectWithBelongsToMany(string $action): void
    {
        factory(User::class)->create();

        $role = factory(Role::class)->create();
        $this->assertInstanceOf(Role::class, $role);

        $role->users()
            ->attach(
                factory(User::class)->create(),
            );

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            {$action}Role(input: {
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
        GRAPHQL)->assertJson([
            'data' => [
                "{$action}Role" => [
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

        $role = Role::query()->firstOrFail();
        $this->assertCount(2, $role->users()->get());
    }

    /** @dataProvider existingModelMutations */
    #[DataProvider('existingModelMutations')]
    public function testSyncWithBelongsToMany(string $action): void
    {
        factory(User::class)->create();

        $role = factory(Role::class)->create();
        $this->assertInstanceOf(Role::class, $role);
        $role->users()
            ->attach(
                factory(User::class)->create(),
            );

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            {$action}Role(input: {
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
        GRAPHQL)->assertJson([
            'data' => [
                "{$action}Role" => [
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

        $role = Role::query()->firstOrFail();
        $this->assertCount(2, $role->users()->get());
    }

    /** @dataProvider existingModelMutations */
    #[DataProvider('existingModelMutations')]
    public function testDisconnectWithBelongsToMany(string $action): void
    {
        $role = factory(Role::class)->create();
        $this->assertInstanceOf(Role::class, $role);

        $role->users()
            ->attach(
                factory(User::class, 2)->create(),
            );

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            {$action}Role(input: {
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
        GRAPHQL)->assertJson([
            'data' => [
                "{$action}Role" => [
                    'id' => '1',
                    'users' => [
                        [
                            'id' => '2',
                        ],
                    ],
                ],
            ],
        ]);

        $role = Role::query()->firstOrFail();
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

    /** @dataProvider existingModelMutations */
    #[DataProvider('existingModelMutations')]
    public function testDisconnectAllRelatedModelsOnEmptySync(string $action): void
    {
        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $role = factory(Role::class)->make();
        $this->assertInstanceOf(Role::class, $role);
        $user->roles()->save($role);

        $this->assertCount(1, $role->users);

        $this->graphQL(/** @lang GraphQL */ "
        mutation {
            {$action}Role(input: {
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
                "{$action}Role" => [
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
        $this->assertInstanceOf(User::class, $user);

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
        $this->assertInstanceOf(User::class, $user);

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
