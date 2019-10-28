<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;
use Nuwave\Lighthouse\Exceptions\DefinitionException;

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
        $this->expectException(DefinitionException::class);

        $this->buildSchema(/* @lang GraphQL */ '
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
        $this->expectException(DefinitionException::class);

        $this->buildSchema(/* @lang GraphQL */ '
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
        $this->expectException(DefinitionException::class);

        $this->buildSchema(/* @lang GraphQL */ '
        type User {
            id: ID!
        }
        
        type Query {
            deleteUser(foo: String, bar: Int): User @delete
        }
        ');
    }

    public function testRequiresRelationWhenUsingAsArgumentResolver(): void
    {
        $this->expectException(DefinitionException::class);

        $this->buildSchema(/* @lang GraphQL */ '
        type Query {
            updateUser(deleteTasks: Tasks @delete): User @update
        }
        
        type User {
            id: ID!
        }
        ');
    }

    public function testUseNestedArgumentResolverDelete(): void
    {
        factory(User::class)->create();
        factory(Task::class, 2)->create([
            'user_id' => 1,
        ]);

        $this->schema = /* @lang GraphQL */ '
        type Query {
            updateUser(
                id: Int
                deleteTasks: [Int!]! @delete(relation: "tasks")
            ): User @update
        }
        
        type User {
            id: Int!
            tasks: [Task!]!
        }
        
        type Task {
            id: Int
        }
        ';

        $this->graphQL(/* @lang GraphQL */ '
        {
            updateUser(id: 1, deleteTasks: [2]) {
                id
                tasks {
                    id
                }
            }
        }
        ')->assertExactJson([
            'data' => [
                'updateUser' => [
                    'id' => 1,
                    'tasks' => [
                        [
                            'id' => 1,
                        ],
                    ],
                ],
            ],
        ]);
    }
}
