<?php

namespace Tests\Integration\Schema\Directives\Fields;

use Illuminate\Support\Arr;
use Tests\DBTestCase;
use Tests\Utils\Models\User;
use Nuwave\Lighthouse\Exceptions\DirectiveException;

class DeleteDirectiveTest extends DBTestCase
{
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
        ' . $this->placeholderQuery();
        $query = "
        mutation {
            deleteUser(id: 1) {
                id
            }
        }
        ";
        $result = $this->execute($schema, $query);

        $this->assertEquals(1, Arr::get($result, 'data.deleteUser.id'));
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
        ' . $this->placeholderQuery();
        $query = "
        mutation {
            deleteUsers(id: [1, 2]) {
                name
            }
        }
        ";
        $result = $this->execute($schema, $query);

        $this->assertCount(2, Arr::get($result, 'data.deleteUsers'));
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
        ' . $this->placeholderQuery();
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
        ' . $this->placeholderQuery();
        $query = "
        mutation {
            deleteUser {
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
        ' . $this->placeholderQuery();
        $query = "
        mutation {
            deleteUser {
                name
            }
        }
        ";
        $this->execute($schema, $query);
    }
}
