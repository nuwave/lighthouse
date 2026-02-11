<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use GraphQL\Type\Definition\Type;
use Illuminate\Container\Container;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\Arguments\UpsertModel;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Tests\DBTestCase;
use Tests\Utils\Models\Company;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

final class UpsertDirectiveTest extends DBTestCase
{
    public function testNestedArgResolver(): void
    {
        $user = factory(User::class)->make();
        $user->id = 1;
        $user->save();

        $task = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $task);
        $task->user()->associate($user);
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
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
            email: String!
            name: String!
        }

        type Mutation {
            upsertUser(name: String!, email: String!): User @upsert(identifyingColumns: ["email"])
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            upsertUser(
                email: "foo@te.st"
                name: "bar"
            ) {
                name
                email
            }
        }
        GRAPHQL)->assertJson([
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

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            upsertUser(
                email: "foo@te.st"
                name: "foo"
            ) {
                name
                email
            }
        }
        GRAPHQL)->assertJson([
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
        $company = factory(Company::class)->make();
        $company->id = 1;
        $company->save();

        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
            email: String!
            name: String!
            company_id: ID!
        }

        type Mutation {
            upsertUser(name: String!, email: String!, company_id: ID!): User @upsert(identifyingColumns: ["name", "company_id"])
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL)->assertJson([
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

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL)->assertJson([
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

    public function testDirectUpsertByIdentifyingColumnsMustNotBeEmpty(): void
    {
        $this->expectException(DefinitionException::class);
        $this->buildSchema(/** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
            email: String!
            name: String!
        }

        type Mutation {
            upsertUser(name: String!, email: String!): User @upsert(identifyingColumns: [])
        }
        GRAPHQL . self::PLACEHOLDER_QUERY);
    }

    public function testNestedUpsertByIdentifyingColumnsMustNotBeEmpty(): void
    {
        $this->expectException(DefinitionException::class);
        $this->buildSchema(/** @lang GraphQL */ <<<'GRAPHQL'
        type Mutation {
            updateUser(input: UpdateUserInput! @spread): User @update
        }

        type Task {
            id: Int
            name: String!
        }

        type User {
            id: Int
            tasks: [Task!]! @hasMany
        }

        input UpdateUserInput {
            id: Int
            tasks: [UpdateTaskInput!] @upsert(relation: "tasks", identifyingColumns: [])
        }

        input UpdateTaskInput {
            id: Int
            name: String
        }
        GRAPHQL . self::PLACEHOLDER_QUERY);
    }

    public function testNestedUpsertByIdentifyingColumn(): void
    {
        $user = factory(User::class)->create();
        $task = factory(Task::class)->make();
        $task->name = 'existing-task';
        $task->difficulty = 1;
        $task->user()->associate($user);
        $task->save();

        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Mutation {
            updateUser(input: UpdateUserInput! @spread): User @update
        }

        type Task {
            id: Int
            name: String!
            difficulty: Int
        }

        type User {
            id: Int
            tasks: [Task!]! @hasMany
        }

        input UpdateUserInput {
            id: Int
            tasks: [UpdateTaskInput!] @upsert(relation: "tasks", identifyingColumns: ["name"])
        }

        input UpdateTaskInput {
            name: String!
            difficulty: Int
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            updateUser(input: {
                id: {$user->id}
                tasks: [
                    {
                        name: "existing-task"
                        difficulty: 2
                    }
                ]
            }) {
                tasks {
                    name
                    difficulty
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'updateUser' => [
                    'tasks' => [
                        [
                            'name' => 'existing-task',
                            'difficulty' => 2,
                        ],
                    ],
                ],
            ],
        ]);

        $task->refresh();

        $this->assertSame(2, $task->difficulty);
        $this->assertSame(1, Task::count());
    }

    public function testDirectUpsertByIdentifyingColumnsRequiresAllConfiguredColumns(): void
    {
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
            email: String
            name: String
        }

        type Mutation {
            upsertUser(name: String!, email: String): User @upsert(identifyingColumns: ["name", "email"])
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            upsertUser(name: "foo") {
                id
            }
        }
        GRAPHQL)->assertGraphQLErrorMessage(UpsertModel::MISSING_IDENTIFYING_COLUMNS_FOR_UPSERT);
    }

    public function testUpsertByIdentifyingColumnWithInputSpread(): void
    {
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
            email: String!
            name: String!
        }

        input UpsertUserInput {
            email: String!
            name: String!
        }

        type Mutation {
            upsertUser(input: UpsertUserInput! @spread): User! @upsert(identifyingColumns: ["email"])
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            upsertUser(input: {
                email: "foo@te.st"
                name: "bar"
            }) {
                email
                name
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'upsertUser' => [
                    'email' => 'foo@te.st',
                    'name' => 'bar',
                ],
            ],
        ]);

        $this->assertSame(1, User::count());

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            upsertUser(input: {
                email: "foo@te.st"
                name: "baz"
            }) {
                email
                name
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'upsertUser' => [
                    'email' => 'foo@te.st',
                    'name' => 'baz',
                ],
            ],
        ]);

        $this->assertSame(1, User::count());
        $this->assertSame('baz', User::firstOrFail()->name);
    }

    public static function resolveType(): Type
    {
        $typeRegistry = Container::getInstance()->make(TypeRegistry::class);

        return $typeRegistry->get('Admin');
    }
}
