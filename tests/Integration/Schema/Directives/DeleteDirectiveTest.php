<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\User;
use Nuwave\Lighthouse\Exceptions\DirectiveException;

class DeleteDirectiveTest extends DBTestCase
{
    /**
     * @test
     */
    public function itDeletesUserAndReturnsIt(): void
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

    /**
     * @test
     */
    public function itDeletesMultipleUsersAndReturnsThem(): void
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

        $this->graphQL('
        mutation {
            deleteUsers(id: [1, 2]) {
                name
            }
        }
        ')->assertJsonCount(2, 'data.deleteUsers');

        $this->assertCount(0, User::all());
    }

    /**
     * @test
     */
    public function itRejectsDefinitionWithNullableArgument(): void
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

        $this->graphQL('
        mutation {
            deleteUser(id: 1) {
                name
            }
        }
        ');
    }

    /**
     * @test
     */
    public function itRejectsDefinitionWithNoArgument(): void
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

        $this->graphQL('
        mutation {
            deleteUser {
                name
            }
        }
        ');
    }

    /**
     * @test
     */
    public function itRejectsDefinitionWithMultipleArguments(): void
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

        $this->graphQL('
        mutation {
            deleteUser {
                name
            }
        }
        ');
    }
}
