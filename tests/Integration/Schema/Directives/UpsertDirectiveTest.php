<?php

namespace Tests\Integration\Schema\Directives;

use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class UpsertDirectiveTest extends DBTestCase
{
    public function testNestedArgResolver(): void
    {
        factory(User::class)->create();
        factory(Task::class)->create([
            'id' => 1,
            'name' => 'old',
        ]);

        $this->schema .= /** @lang GraphQL */ '
        type Mutation {
            updateUser(input: UpdateUserInput! @spread): User @update
        }

        type Task {
            id: Int
            name: String!
        }

        type User {
            name: String
            tasks: [Task!]! @hasMany
        }

        input UpdateUserInput {
            id: Int
            name: String
            tasks: [UpdateTaskInput!] @upsert(relation: "tasks")
        }

        input UpdateTaskInput {
            id: Int
            name: String
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            updateUser(input: {
                id: 1
                name: "foo"
                tasks: [
                    {
                        id: 1
                        name: "updated"
                    }
                    {
                        id: 2
                        name: "new"
                    }
                ]
            }) {
                name
                tasks {
                    id
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'updateUser' => [
                    'name' => 'foo',
                    'tasks' => [
                        [
                            'id' => 1,
                            'name' => 'updated',
                        ],
                        [
                            'id' => 2,
                            'name' => 'new',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testNestedInsertOnInputList(): void
    {
        factory(User::class)->create();
        $this->schema .= /** @lang GraphQL */ '
        type Mutation {
            updateUser(input: UpdateUserInput! @spread): User @update
        }

        type Task {
            id: Int
            name: String!
        }

        type User {
            name: String
            tasks: [Task!]! @hasMany
        }

        input UpdateUserInput {
            id: Int
            name: String
            tasks: [UpdateTaskInput!] @upsert(relation: "tasks")
        }

        input UpdateTaskInput {
            id: Int
            name: String
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            updateUser(input: {
                id: 1
                name: "foo"
                tasks: [
                    {
                        name: "foo"
                    }
                    {
                        name: "bar"
                    }
                ]
            }) {
                name
                tasks {
                    name
                }
            }
        }')->assertJson([
            'data' => [
                'updateUser' => [
                    'name' => 'foo',
                    'tasks' => [
                        [
                            'name' => 'foo',
                        ],
                        [
                            'name' => 'bar',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testUpsertUsingInterface(): void
    {
        $this->schema .= /** @lang GraphQL */ <<<GRAPHQL
        type Mutation {
            upsertUser(input: UpsertUserInput! @spread): IUser @upsert
        }

        interface IUser
        @interface(resolveType: "{$this->qualifyTestResolver('resolveType')}")
        @model(class: "Tests\\\\Utils\\\\Models\\\\User") {
            name: String
        }

        type Admin implements IUser {
            id: ID!
            name: String
        }

        input UpsertUserInput {
            name: String
        }
GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            upsertUser(input: {
                name: "foo"
            }) {
                ... on Admin {
                    id
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'upsertUser' => [
                    'id' => 1,
                    'name' => 'foo',
                ],
            ],
        ]);
    }

    public function resolveType(): Type
    {
        return app(TypeRegistry::class)->get('Admin');
    }
}
