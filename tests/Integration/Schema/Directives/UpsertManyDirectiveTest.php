<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use GraphQL\Type\Definition\Type;
use Illuminate\Container\Container;
use Nuwave\Lighthouse\Execution\Arguments\UpsertModel;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

final class UpsertManyDirectiveTest extends DBTestCase
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
            tasks: [UpdateTaskInput!] @upsertMany(relation: "tasks")
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
            tasks: [UpdateTaskInput!] @upsertMany(relation: "tasks")
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
            upsertUsers(inputs: [UpsertUserInput!]!): [IUser!]! @upsertMany
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
            upsertUsers(inputs: [
                {
                    name: "foo"
                }
                {
                    name: "bar"
                }
            ]) {
                ... on Admin {
                    id
                    name
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'upsertUsers' => [
                    [
                        'id' => 1,
                        'name' => 'foo',
                    ],
                    [
                        'id' => 2,
                        'name' => 'bar',
                    ],
                ],
            ],
        ]);
    }

    public function testDirectUpsertManyByIdentifyingColumn(): void
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
            upsertUsers(inputs: [UpsertUserInput!]!): [User!]! @upsertMany(identifyingColumns: ["email"])
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            upsertUsers(inputs: [
                { email: "foo@te.st", name: "bar" }
                { email: "baz@te.st", name: "qux" }
            ]) {
                email
                name
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'upsertUsers' => [
                    [
                        'email' => 'foo@te.st',
                        'name' => 'bar',
                    ],
                    [
                        'email' => 'baz@te.st',
                        'name' => 'qux',
                    ],
                ],
            ],
        ]);

        $this->assertSame(2, User::count());

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            upsertUsers(inputs: [
                { email: "foo@te.st", name: "updated" }
                { email: "baz@te.st", name: "qux" }
            ]) {
                email
                name
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'upsertUsers' => [
                    [
                        'email' => 'foo@te.st',
                        'name' => 'updated',
                    ],
                    [
                        'email' => 'baz@te.st',
                        'name' => 'qux',
                    ],
                ],
            ],
        ]);

        $this->assertSame(2, User::count());
        $this->assertSame('updated', User::where('email', 'foo@te.st')->firstOrFail()->name);
    }

    public function testDirectUpsertManyByIdentifyingColumnsRequiresAllConfiguredColumns(): void
    {
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
            email: String
            name: String
        }

        input UpsertUserInput {
            email: String
            name: String!
        }

        type Mutation {
            upsertUsers(inputs: [UpsertUserInput!]!): [User!]! @upsertMany(identifyingColumns: ["name", "email"])
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            upsertUsers(inputs: [{ name: "foo" }]) {
                id
            }
        }
        GRAPHQL)->assertGraphQLErrorMessage(UpsertModel::MISSING_IDENTIFYING_COLUMNS_FOR_UPSERT);
    }

    public function testNestedUpsertManyByIdDoesNotModifySiblingParentsRelatedModel(): void
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
            tasks: [UpdateTaskInput!] @upsertMany(relation: "tasks")
        }

        input UpdateTaskInput {
            id: Int
            name: String
        }
        GRAPHQL;

        $this->graphQL(
            /** @lang GraphQL */ <<<'GRAPHQL'
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

    public function testNestedUpsertManyByIdentifyingColumnDoesNotModifySiblingParentsRelatedModel(): void
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
            tasks: [UpdateTaskInput!] @upsertMany(relation: "tasks", identifyingColumns: ["name"])
        }

        input UpdateTaskInput {
            name: String!
            difficulty: Int
        }
        GRAPHQL;

        $this->graphQL(
            /** @lang GraphQL */ <<<'GRAPHQL'
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
