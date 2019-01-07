<?php

namespace Tests\Integration\Schema\Directives\Fields\CreateDirectiveTests\RelationshipTests;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

class BelongsToManyTest extends DBTestCase
{
    /**
     * @test
     */
    public function itCanCreateWithNewBelongsToMany(): void
    {
        $this->schema = '
        type Role {
            id: ID!
            name: String
            users: [User] @belongsToMany
        }
        
        type User {
            id: ID
            name: String
        }
        
        type Mutation {
            createRole(input: CreateRoleInput!): Role @create(flatten: true)
        }
        
        input CreateRoleInput {
            name: String
            users: CreateUserRelation
        }
        
        input CreateUserRelation {
            create: [CreateUserInput!]
        }
        
        input CreateUserInput {
            name: String
        }
        '.$this->placeholderQuery();

        $this->query('
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

        $this->schema = '
        type Role {
            id: ID!
            name: String
            users: [User] @belongsToMany
        }
        
        type User {
            id: ID
            name: String
        }
        
        type Mutation {
            createRole(input: CreateRoleInput!): Role @create(flatten: true)
        }
        
        input CreateRoleInput {
            name: String
            users: CreateUserRelation
        }
        
        input CreateUserRelation {
            connect: [ID]
        }
        
        '.$this->placeholderQuery();

        $this->query('
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
}
