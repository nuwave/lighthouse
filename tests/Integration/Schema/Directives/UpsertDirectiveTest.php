<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use GraphQL\Type\Definition\Type;
use Illuminate\Container\Container;
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
        $task->id = 1;
        $task->user()->associate($user);
        $task->save();
        $this->assertInstanceOf(Task::class, $task);
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

    public function testNestedUpsertByIdDoesNotModifySiblingParentsRelatedModel(): void
    {
        $userA = factory(User::class)->create();
        $userB = factory(User::class)->create();
        $taskA = factory(Task::class)->make();
        $taskA->name = 'from-user-a';
        $taskA->user()->associate($userA);
        $taskA->save();

        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
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
            tasks: [UpdateTaskInput!] @upsert(relation: "tasks")
        }

        input UpdateTaskInput {
            id: Int
            name: String
        }
        GRAPHQL;

        $this->graphQL(
            /** @lang GraphQL */
            <<<'GRAPHQL'
        mutation ($userID: Int!, $taskID: Int!) {
            updateUser(input: {
                id: $userID
                tasks: [{ id: $taskID, name: "hacked" }]
            }) {
                id
            }
        }
        GRAPHQL,
            [
                'userID' => $userB->id,
                'taskID' => $taskA->id,
            ],
        )->assertGraphQLErrorMessage(UpsertModel::CANNOT_UPSERT_UNRELATED_MODEL);

        $taskA->refresh();
        $this->assertSame($userA->id, $taskA->user_id);
        $this->assertSame('from-user-a', $taskA->name);
    }

    public function testNestedUpsertByIdentifyingColumnDoesNotModifySiblingParentsRelatedModel(): void
    {
        $userA = factory(User::class)->create();
        $userB = factory(User::class)->create();
        $taskA = factory(Task::class)->make();
        $taskA->name = 'same-name';
        $taskA->difficulty = 1;
        $taskA->user()->associate($userA);
        $taskA->save();

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
            id: Int
            name: String!
            difficulty: Int
        }
        GRAPHQL;

        $this->graphQL(
            /** @lang GraphQL */
            <<<'GRAPHQL'
        mutation ($userID: Int!) {
            updateUser(input: {
                id: $userID
                tasks: [{ name: "same-name", difficulty: 2 }]
            }) {
                id
                tasks {
                    name
                    difficulty
                }
            }
        }
        GRAPHQL,
            [
                'userID' => $userB->id,
            ],
        )->assertGraphQLErrorMessage(UpsertModel::CANNOT_UPSERT_UNRELATED_MODEL);

        $taskA->refresh();
        $this->assertSame($userA->id, $taskA->user_id);
        $this->assertSame(1, $taskA->difficulty);
    }

    public static function resolveType(): Type
    {
        $typeRegistry = Container::getInstance()->make(TypeRegistry::class);

        return $typeRegistry->get('Admin');
    }
}
