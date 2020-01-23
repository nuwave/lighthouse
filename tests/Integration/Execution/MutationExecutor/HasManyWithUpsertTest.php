<?php

namespace Tests\Integration\Execution\MutationExecutor;

use Tests\DBTestCase;
use Tests\Utils\Models\Role;

class HasManyWithUpsertTest extends DBTestCase
{
    protected $schema = /** @lang GraphQL */ '
    type User {
        id: ID!
        name: String!
        rolesPivot: [RoleUserPivot!] @hasMany
    }
    
    type RoleUserPivot {
        id: ID
        role: Role! @belongsTo
        user: User! @belongsTo
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
        rolesPivot: UpsertRolesPivotRelation
    }
    
    input UpsertRolesPivotRelation {
        upsert: [UpsertRolePivotInput!]
    }
    
    input UpsertRolePivotInput {
        id: ID
        user: UpsertRolePivotUserRelation
        role: UpsertRolePivotRoleRelation
    }
    
    input UpsertRolePivotUserRelation {
        connect: ID
    }
    
    input UpsertRolePivotRoleRelation {
        connect: ID
        upsert: UpsertRoleInput
    }
    
    input UpsertRoleInput {
        id: ID
        name: String!
    }
    '.self::PLACEHOLDER_QUERY;

    public function testCanUpsertHasManyWithConnectBelongsTo(): void
    {
        $role = factory(Role::class)->create([
            'name' => 'bar',
        ]);

        $this->graphQL(/** @lang GraphQL */ "
        mutation {
            upsertUser(input: {
                name: \"foo\"
                rolesPivot: {
                    upsert: [{
                        role: {
                            connect: {$role->id}
                        }
                    }]
                }
            }) {
                id
                name
                rolesPivot {
                    id
                    role {
                        id
                        name
                    }
                    user {
                        id
                        name
                    }
                }
            }
        }
        ")->assertJson([
            'data' => [
                'upsertUser' => [
                    'id' => '1',
                    'name' => 'foo',
                    'rolesPivot' => [
                        [
                            'id' => '1',
                            'role' => [
                                'id' => '1',
                                'name' => 'bar',
                            ],
                            'user' => [
                                'id' => '1',
                                'name' => 'foo',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testCanUpsertHasManyWithUpsertBelongsTo(): void
    {
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
                rolesPivot {
                    id
                    role {
                        id
                        name
                    }
                    user {
                        id
                        name
                    }
                }
            }
        }
        ')->assertJson([
            'data' => [
                'upsertUser' => [
                    'id' => '1',
                    'name' => 'foo',
                    'rolesPivot' => [
                        [
                            'id' => '1',
                            'role' => [
                                'id' => '1',
                                'name' => 'bar',
                            ],
                            'user' => [
                                'id' => '1',
                                'name' => 'foo',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
