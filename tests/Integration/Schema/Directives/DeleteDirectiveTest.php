<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\User;
use Nuwave\Lighthouse\Exceptions\DirectiveException;

class DeleteDirectiveTest extends DBTestCase
{
    public function testDeletesUserAndReturnsIt(): void
    {
        factory(User::class)->create();

        $this->schema .= '
        type User {
            id: ID!
        }
        
        type Mutation {
            deleteUser(id: ID!): User @delete
        }
        ';

        $this->graphQL('
        mutation {
            deleteUser(id: 1) {
                id
            }
        }
        ')->assertJson([
            'data' => [
                'deleteUser' => [
                    'id' => 1,
                ],
            ],
        ]);

        $this->assertCount(0, User::all());
    }

    public function testDeletesMultipleUsersAndReturnsThem(): void
    {
        factory(User::class, 2)->create();

        $this->schema .= '
        type User {
            id: ID!
            name: String
        }
        
        type Mutation {
            deleteUsers(id: [ID!]!): [User!]! @delete
        }
        ';

        $this->graphQL('
        mutation {
            deleteUsers(id: [1, 2]) {
                name
            }
        }
        ')->assertJsonCount(2, 'data.deleteUsers');

        $this->assertCount(0, User::all());
    }

    public function testRejectsDefinitionWithNullableArgument(): void
    {
        $this->expectException(DirectiveException::class);

        $this->buildSchema('
        type User {
            id: ID!
            name: String
        }
        
        type Query {
            deleteUser(id: ID): User @delete
        }
        ');
    }

    public function testRejectsDefinitionWithNoArgument(): void
    {
        $this->expectException(DirectiveException::class);

        $this->buildSchema('
        type User {
            id: ID!
        }
        
        type Query {
            deleteUser: User @delete
        }
        ');
    }

    public function testRejectsDefinitionWithMultipleArguments(): void
    {
        $this->expectException(DirectiveException::class);

        $this->buildSchema('
        type User {
            id: ID!
        }
        
        type Query {
            deleteUser(foo: String, bar: Int): User @delete
        }
        ');
    }
}
