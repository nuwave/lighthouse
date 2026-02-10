<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use GraphQL\Type\Definition\Type;
use Illuminate\Container\Container;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Tests\DBTestCase;
use Tests\Utils\Models\Company;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

final class UpsertDirectiveTest extends DBTestCase
{
    public function testNestedArgResolver(): void
    {
        factory(User::class)->create();

        $task = factory(Task::class)->create();
        $this->assertInstanceOf(Task::class, $task);
        $task->id = 1;
        $task->name = 'old';
        $task->save();

        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL)->assertJson([
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

        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
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
        }
        GRAPHQL)->assertJson([
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
            upsertUser(input: UpsertUserInput! @spread): IUser! @upsert
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

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL)->assertJson([
            'data' => [
                'upsertUser' => [
                    'id' => 1,
                    'name' => 'foo',
                ],
            ],
        ]);
    }

    public function testDirectUpsertByIdentifyingColumn(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type User {
            id: ID!
            email: String!
            name: String!
        }

        type Mutation {
            upsertUser(name: String!, email: String!): User @upsert(identifyingColumns: ["email"])
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            upsertUser(
                email: "foo@te.st"
                name: "bar"
            ) {
                name
                email
            }
        }
        ')->assertJson([
            'data' => [
                'upsertUser' => [
                    'email' => 'foo@te.st',
                    'name' => 'bar',
                ],
            ],
        ]);

        $user = User::firstOrFail();

        $this->assertSame('bar', $user->name);
        $this->assertSame('foo@te.st', $user->email);

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            upsertUser(
                email: "foo@te.st"
                name: "foo"
            ) {
                name
                email
            }
        }
        ')->assertJson([
            'data' => [
                'upsertUser' => [
                    'email' => 'foo@te.st',
                    'name' => 'foo',
                ],
            ],
        ]);

        $user->refresh();

        $this->assertSame('foo', $user->name);
        $this->assertSame('foo@te.st', $user->email);
    }

    public function testDirectUpsertByIdentifyingColumns(): void
    {
        $company = factory(Company::class)->create(['id' => 1]);

        $this->schema
            /** @lang GraphQL */
            .= '
        type User {
            id: ID!
            email: String!
            name: String!
            company_id: ID!
        }

        type Mutation {
            upsertUser(name: String!, email: String!, company_id:ID!): User @upsert(identifyingColumns: ["name", "company_id"])
        }
        ';

        $this->graphQL(
            /** @lang GraphQL */
            '
        mutation {
            upsertUser(
                email: "foo@te.st"
                name: "bar"
                company_id: 1
            ) {
                name
                email
                company_id
            }
        }
        ',
        )->assertJson([
            'data' => [
                'upsertUser' => [
                    'email' => 'foo@te.st',
                    'name' => 'bar',
                    'company_id' => 1,
                ],
            ],
        ]);

        $user = User::firstOrFail();

        $this->assertSame('bar', $user->name);
        $this->assertSame('foo@te.st', $user->email);
        $this->assertSame(1, $user->company_id);

        $this->graphQL(
            /** @lang GraphQL */
            '
        mutation {
            upsertUser(
                email: "bar@te.st"
                name: "bar"
                company_id: 1
            ) {
                name
                email
                company_id
            }
        }
        ',
        )->assertJson([
            'data' => [
                'upsertUser' => [
                    'email' => 'bar@te.st',
                    'name' => 'bar',
                    'company_id' => $company->id,
                ],
            ],
        ]);

        $user->refresh();

        $this->assertSame('bar', $user->name);
        $this->assertSame('bar@te.st', $user->email);
    }

    public static function resolveType(): Type
    {
        $typeRegistry = Container::getInstance()->make(TypeRegistry::class);

        return $typeRegistry->get('Admin');
    }
}
