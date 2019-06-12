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
    }

    input CreateRoleInput {
        name: String
        users: CreateUserRelation
    }
    
    input CreateUserRelation {
        create: [CreateUserInput!]
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
        delete: [ID!]
        connect: [ID!]
        sync: [ID!]
        disconnect: [ID!]
    }
    
    input UpdateUserInput {
        id: ID!
        name: String
    }
    ';

    protected function setUp(): void
    {
        parent::setUp();

        $this->schema .= $this->placeholderQuery();
    }

    /**
     * @test
     */
    public function itCanCreateWithNewBelongsToMany(): void
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

    /**
     * @test
     */
    public function itCanCreateAndConnectWithBelongsToMany(): void
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

    /**
     * @test
     */
    public function itCanCreateWithBelongsToMany(): void
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

    /**
     * @test
     */
    public function itCanUpdateWithBelongsToMany(): void
    {
        factory(Role::class)
            ->create([
                'name' => 'is_admin',
            ])
            ->users()
            ->attach(
                factory(User::class, 2)->create()
            );

        $this->graphQL('
        mutation {
            updateRole(input: {
                id: 1
                name: "is_user"
                users: {
                    update: [{
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

        /** @var Role $role */
        $role = Role::first();
        $this->assertCount(2, $role->users()->get());
        $this->assertSame('is_user', $role->name);
    }

    /**
     * @test
     */
    public function itCanDeleteWithBelongsToMany(): void
    {
        factory(Role::class)
            ->create([
                'name' => 'is_admin',
            ])
            ->users()
            ->attach(
                factory(User::class, 2)->create()
            );

        $this->graphQL('
        mutation {
            updateRole(input: {
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
        ')->assertJson([
            'data' => [
                'updateRole' => [
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
     * @test
     */
    public function itCanConnectWithBelongsToMany(): void
    {
        factory(User::class)->create();
        factory(Role::class)
            ->create()
            ->users()
            ->attach(
                factory(User::class)->create()
            );

        $this->graphQL('
        mutation {
            updateRole(input: {
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
        ')->assertJson([
            'data' => [
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

        /** @var Role $role */
        $role = Role::first();
        $this->assertCount(2, $role->users()->get());
    }

    /**
     * @test
     */
    public function itCanSyncWithBelongsToMany(): void
    {
        factory(User::class)->create();
        factory(Role::class)
            ->create()
            ->users()
            ->attach(
                factory(User::class)->create()
            );

        $this->graphQL('
        mutation {
            updateRole(input: {
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
        ')->assertJson([
            'data' => [
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

        /** @var Role $role */
        $role = Role::first();
        $this->assertCount(2, $role->users()->get());
    }

    /**
     * @test
     */
    public function itCanDisconnectWithBelongsToMany(): void
    {
        factory(Role::class)
            ->create()
            ->users()
            ->attach(
                factory(User::class, 2)->create()
            );

        $this->graphQL('
        mutation {
            updateRole(input: {
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
        ')->assertJson([
            'data' => [
                'updateRole' => [
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

    /**
     * @test
     */
    public function itCanSyncExistingUsersDuringCreateToABelongsToManyRelation(): void
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

    /**
     * @test
     */
    public function itCanDisconnectAllRelatedModelsOnEmptySync(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();
        /** @var Role $role */
        $role = $user->roles()->save(
            factory(Role::class)->make()
        );

        $this->assertCount(1, $role->users);

        $this->graphQL('
        mutation {
            updateRole(input: {
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
        ')->assertJson([
            'data' => [
                'updateRole' => [
                    'id' => '1',
                    'users' => [],
                ],
            ],
        ]);

        $role->refresh();

        $this->assertCount(0, $role->users);
    }
}
