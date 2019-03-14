<?php

namespace Tests\Integration\Schema\Directives\Fields\UpdateDirectiveTests\RelationshipTests;

use Tests\DBTestCase;
use Tests\Utils\Models\Role;
use Tests\Utils\Models\User;

class BelongsToManyTest extends DBTestCase
{
    private $schemaBase = '
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
        updateRole(input: UpdateRoleInput!): Role @update(flatten: true)
    }
    
    input UpdateRoleInput {
        id: ID!
        name: String
        users: UpdateUserRelation
    }
    
    input UpdateUserInput {
        id: ID!
        name: String
    }
    
    input CreateUserInput {
        name: String
    }
    ';

    public function setUp(): void
    {
        parent::setUp();

        $this->schema = $this->schemaBase . $this->placeholderQuery();
    }

    /**
     * @test
     */
    public function itCanCreateWithBelongsToMany(): void
    {
        factory(Role::class)->create([
            'name' => 'is_admin',
        ]);

        $this->schema .= '
        input UpdateUserRelation {
            create: [CreateUserInput!]
        }
        ';

        $this->query('
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

        $this->schema .= '
        input UpdateUserRelation {
            update: [UpdateUserInput!]
        }
        ';

        $this->query('
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

        $this->schema .= '
        input UpdateUserRelation {
            delete: [ID!]
        }
        ';

        $this->query('
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

        $this->schema .= '
        input UpdateUserRelation {
            connect: [ID!]
        }
        ';

        $this->query('
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

        $this->schema .= '
        input UpdateUserRelation {
            sync: [ID!]
        }
        ';

        $this->query('
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

        $this->schema .= '
        input UpdateUserRelation {
            disconnect: [ID!]
        }
        ';

        $this->query('
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
}
