<?php

namespace Tests\Integration\Execution\MutationExecutor;

use Tests\DBTestCase;
use Tests\Utils\Models\Role;
use Tests\Utils\Models\User;

class BelongsToManyTest extends DBTestCase
{
    protected $schema = '
    type Role {
        id: ID!
        name: String
        users: [User!] @belongsToMany
    }
    
    type User {
        id: ID!
        name: String
    }
    
    type Mutation {
        createRole(input: CreateRoleInput! @spread): Role @create
        updateRole(input: UpdateRoleInput! @spread): Role @update
        upsertRole(input: UpsertRoleInput! @spread): Role @upsert
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
        disconnect: [ID!]
    }
    
    input UpdateUserInput {
        id: ID!
        name: String
    }

    input UpsertRoleInput {
        id: ID!
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
        id: ID!
        name: String
    }
    '.self::PLACEHOLDER_QUERY;

    public function testCanCreateWithNewBelongsToMany(): void
    {
        $this->graphQL('
        mutation {
            createRole(input: {
                name: "foobar"
                users: {
                    create: [{
                        name: "bar"
                    },
                    {
                        name: "foo"
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

    public function testCanUpsertWithBelongsToManyOnNonExistentData(): void
    {
        $this->graphQL('
        mutation {
            upsertRole(input: {
                id: 1
                name: "is_user"
                users: {
                    upsert: [{
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
        ')->assertJson([
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

    public function testCanCreateAndConnectWithBelongsToMany(): void
    {
        factory(User::class)->create(['name' => 'user_one']);
        factory(User::class)->create(['name' => 'user_two']);

        $this->graphQL('
        mutation {
            createRole(input: {
                name: "foobar"
                users: {
                    connect: [
                        1,2
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

    public function testCanUpsertUsingCreationAndConnectWithBelongsToMany(): void
    {
        factory(User::class)->create(['name' => 'user_one']);
        factory(User::class)->create(['name' => 'user_two']);

        $this->graphQL('
        mutation {
            upsertRole(input: {
                id: 1
                name: "foobar"
                users: {
                    connect: [
                        1,2
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

    public function testCanCreateWithBelongsToMany(): void
    {
        factory(Role::class)->create([
            'name' => 'is_admin',
        ]);

        $this->graphQL('
        mutation {
            updateRole(input: {
                id: 1
                name: "is_user"
                users: {
                    create: [{
                        name: "user1"
                    },
                    {
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

    public function testCanUpsertUsingCreationWithBelongsToMany(): void
    {
        factory(Role::class)->create([
            'name' => 'is_admin',
        ]);

        $this->graphQL('
        mutation {
            updateRole(input: {
                id: 1
                name: "is_user"
                users: {
                    upsert: [{
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

    public function actionsOverExistingDataProvider()
    {
        yield ['Update action' => 'update'];
        yield ['Upsert action' => 'upsert'];
    }

    /**
     * @dataProvider actionsOverExistingDataProvider
     */
    public function testCanUpdateWithBelongsToMany($action): void
    {
        factory(Role::class)
            ->create([
                'name' => 'is_admin',
            ])
            ->users()
            ->attach(
                factory(User::class, 2)->create()
            );

        $this->graphQL("
        mutation {
            ${action}Role(input: {
                id: 1
                name: \"is_user\"
                users: {
                    ${action}: [{
                        id: 1
                        name: \"user1\"
                    },
                    {
                        id: 2
                        name: \"user2\"
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
        ")->assertJson([
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
     * @dataProvider actionsOverExistingDataProvider
     */
    public function testCanDeleteWithBelongsToMany($action): void
    {
        factory(Role::class)
            ->create([
                'name' => 'is_admin',
            ])
            ->users()
            ->attach(
                factory(User::class, 2)->create()
            );

        $this->graphQL("
        mutation {
            ${action}Role(input: {
                id: 1
                name: \"is_user\"
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
        ")->assertJson([
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
        $role = Role::first();
        $this->assertCount(1, $role->users()->get());
        $this->assertSame('is_user', $role->name);

        $this->assertNull(User::find(1));
        $this->assertNotNull(User::find(2));
    }

    /**
     * @dataProvider actionsOverExistingDataProvider
     */
    public function testCanConnectWithBelongsToMany($action): void
    {
        factory(User::class)->create();
        factory(Role::class)
            ->create()
            ->users()
            ->attach(
                factory(User::class)->create()
            );

        $this->graphQL("
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
        ")->assertJson([
            'data' => [
                "${action}Role" => [
                    'id' => '1',
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

        /** @var Role $role */
        $role = Role::first();
        $this->assertCount(2, $role->users()->get());
    }

    /**
     * @dataProvider actionsOverExistingDataProvider
     */
    public function testCanSyncWithBelongsToMany($action): void
    {
        factory(User::class)->create();
        factory(Role::class)
            ->create()
            ->users()
            ->attach(
                factory(User::class)->create()
            );

        $this->graphQL("
        mutation {
            ${action}Role(input: {
                id: 1
                users: {
                    sync: [1,2]
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

        /** @var Role $role */
        $role = Role::first();
        $this->assertCount(2, $role->users()->get());
    }

    /**
     * @dataProvider actionsOverExistingDataProvider
     */
    public function testCanDisconnectWithBelongsToMany($action): void
    {
        factory(Role::class)
            ->create()
            ->users()
            ->attach(
                factory(User::class, 2)->create()
            );

        $this->graphQL("
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
        ")->assertJson([
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
        $role = Role::first();
        $this->assertCount(1, $role->users()->get());

        $this->assertNotNull(User::find(1));
        $this->assertNotNull(User::find(2));
    }

    public function testCanSyncExistingUsersDuringCreateToABelongsToManyRelation(): void
    {
        factory(User::class, 2)->create();

        $this->graphQL('
        mutation {
            createRole(input: {
                name: "foobar"
                users: {
                    sync: [
                        1,2
                    ]
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

    public function testCanSyncExistingUsersDuringCreateUsingUpsertToABelongsToManyRelation(): void
    {
        factory(User::class, 2)->create();

        $this->graphQL('
        mutation {
            upsertRole(input: {
                id: 1
                name: "foobar"
                users: {
                    sync: [
                        1,2
                    ]
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
     * @dataProvider actionsOverExistingDataProvider
     */
    public function testCanDisconnectAllRelatedModelsOnEmptySync($action): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();
        /** @var Role $role */
        $role = $user->roles()->save(
            factory(Role::class)->make()
        );

        $this->assertCount(1, $role->users);

        $this->graphQL("
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
}
