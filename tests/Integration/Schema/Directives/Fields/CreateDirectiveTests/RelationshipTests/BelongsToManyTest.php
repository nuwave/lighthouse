<?php

namespace Tests\Integration\Schema\Directives\Fields\CreateDirectiveTests\RelationshipTests;

use Tests\DBTestCase;
use Illuminate\Support\Arr;
use Tests\Utils\Models\User;

class BelongsToManyTest extends DBTestCase
{
    /**
     * @test
     */
    public function itCanCreateWithNewBelongsToMany()
    {
        $schema = '
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
        $query = '
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
        ';
        $result = $this->execute($schema, $query);
        $this->assertSame('1', Arr::get($result, 'data.createRole.id'));
        $this->assertSame('foobar', Arr::get($result, 'data.createRole.name'));
        $this->assertSame('1', Arr::get($result, 'data.createRole.users.0.id'));
        $this->assertSame('bar', Arr::get($result, 'data.createRole.users.0.name'));
    }

    /**
     * @test
     */
    public function itCanCreateAndConnectWithBelongsToMany()
    {
        factory(User::class)->create(['name' => 'user_one']);
        factory(User::class)->create(['name' => 'user_two']);

        $schema = '
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
        $query = '
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
        ';
        $result = $this->execute($schema, $query);
        $this->assertSame('1', Arr::get($result, 'data.createRole.id'));
        $this->assertSame('foobar', Arr::get($result, 'data.createRole.name'));
        $this->assertSame('1', Arr::get($result, 'data.createRole.users.0.id'));
        $this->assertSame('user_one', Arr::get($result, 'data.createRole.users.0.name'));
        $this->assertSame('2', Arr::get($result, 'data.createRole.users.1.id'));
        $this->assertSame('user_two', Arr::get($result, 'data.createRole.users.1.name'));
    }
}