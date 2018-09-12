<?php

namespace Tests\Integration\Schema\Directives\Fields;

use Tests\DBTestCase;
use Tests\Utils\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Exceptions\DirectiveException;

class DeleteDirectiveTest extends DBTestCase
{
    use RefreshDatabase;

    /** @test */
    public function itDeletesUserAndReturnsIt()
    {
        factory(User::class)->create();

        $schema = '
        type User {
            id: ID!
        }
        
        type Mutation {
            deleteUser(id: ID!): User @delete
        }
        
        type Query {
            dummy: Int
        }
        ';
        $query = "
        mutation {
            deleteUser(id: 1) {
                id
            }
        }
        ";
        $result = $this->execute($schema, $query);

        $this->assertEquals(1, array_get($result, 'data.deleteUser.id'));
        $this->assertCount(0, User::all());
    }

    /** @test */
    public function itDeletesMultipleUsersAndReturnsThem()
    {
        factory(User::class, 2)->create();

        $schema = '
        type User {
            id: ID!
            name: String
        }
        
        type Mutation {
            deleteUsers(id: [ID!]!): [User!]! @delete
        }
        
        type Query {
            dummy: Int
        }
        ';
        $query = "
        mutation {
            deleteUsers(id: [1, 2]) {
                name
            }
        }
        ";
        $result = $this->execute($schema, $query);

        $this->assertCount(2, array_get($result, 'data.deleteUsers'));
        $this->assertCount(0, User::all());
    }

    /** @test */
    public function itRejectsDefinitionWithNullableArgument()
    {
        $this->expectException(DirectiveException::class);
        $schema = '
        type User {
            id: ID!
            name: String
        }
        
        type Mutation {
            deleteUser(id: ID): User @delete
        }
        
        type Query {
            dummy: Int
        }
        ';
        $query = "
        mutation {
            deleteUser(id: 1) {
                name
            }
        }
        ";
        $this->execute($schema, $query);
    }

    /** @test */
    public function itRejectsDefinitionWithNoArgument()
    {
        $this->expectException(DirectiveException::class);
        $schema = '
        type User {
            id: ID!
            name: String
        }
        
        type Mutation {
            deleteUser: User @delete
        }
        
        type Query {
            dummy: Int
        }
        ';
        $query = "
        mutation {
            deleteUser(id: 1) {
                name
            }
        }
        ";
        $this->execute($schema, $query);
    }

    /** @test */
    public function itRejectsDefinitionWithMultipleArguments()
    {
        $this->expectException(DirectiveException::class);
        $schema = '
        type User {
            id: ID!
            name: String
        }
        
        type Mutation {
            deleteUser(foo: String, bar: Int): User @delete
        }
        
        type Query {
            dummy: Int
        }
        ';
        $query = "
        mutation {
            deleteUser(id: 1) {
                name
            }
        }
        ";
        $this->execute($schema, $query);
    }
}
