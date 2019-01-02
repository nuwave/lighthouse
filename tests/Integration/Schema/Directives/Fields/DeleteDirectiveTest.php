<?php

namespace Tests\Integration\Schema\Directives\Fields;

use Tests\DBTestCase;
use Illuminate\Support\Arr;
use Tests\Utils\Models\User;
use Nuwave\Lighthouse\Exceptions\DirectiveException;

class DeleteDirectiveTest extends DBTestCase
{
    /** @test */
    public function itDeletesUserAndReturnsIt()
    {
        factory(User::class)->create();

        $this->schema = '
        type User {
            id: ID!
        }
        
        type Mutation {
            deleteUser(id: ID!): User @delete
        }
        '.$this->placeholderQuery();
        $query = '
        mutation {
            deleteUser(id: 1) {
                id
            }
        }
        ';

        $this->query($query)->assertJson([
            'data' => [
                'deleteUser' => [
                    'id' => 1
                ]
            ]
        ]);
        $this->assertCount(0, User::all());
    }

    /** @test */
    public function itDeletesMultipleUsersAndReturnsThem()
    {
        factory(User::class, 2)->create();

        $this->schema = '
        type User {
            id: ID!
            name: String
        }
        
        type Mutation {
            deleteUsers(id: [ID!]!): [User!]! @delete
        }
        '.$this->placeholderQuery();
        $query = '
        mutation {
            deleteUsers(id: [1, 2]) {
                name
            }
        }
        ';

        $this->query($query)->assertJsonCount(2, 'data.deleteUsers');
        $this->assertCount(0, User::all());
    }

    /** @test */
    public function itRejectsDefinitionWithNullableArgument()
    {
        $this->expectException(DirectiveException::class);
        $this->schema = '
        type User {
            id: ID!
            name: String
        }
        
        type Mutation {
            deleteUser(id: ID): User @delete
        }
        '.$this->placeholderQuery();
        $query = '
        mutation {
            deleteUser(id: 1) {
                name
            }
        }
        ';
        $this->query($query);
    }

    /** @test */
    public function itRejectsDefinitionWithNoArgument()
    {
        $this->expectException(DirectiveException::class);
        $this->schema = '
        type User {
            id: ID!
            name: String
        }
        
        type Mutation {
            deleteUser: User @delete
        }
        '.$this->placeholderQuery();
        $query = '
        mutation {
            deleteUser {
                name
            }
        }
        ';
        $this->query($query);
    }

    /** @test */
    public function itRejectsDefinitionWithMultipleArguments()
    {
        $this->expectException(DirectiveException::class);
        $this->schema = '
        type User {
            id: ID!
            name: String
        }
        
        type Mutation {
            deleteUser(foo: String, bar: Int): User @delete
        }
        '.$this->placeholderQuery();
        $query = '
        mutation {
            deleteUser {
                name
            }
        }
        ';
        $this->query($query);
    }
}
