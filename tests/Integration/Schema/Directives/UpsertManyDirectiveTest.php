<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use GraphQL\Type\Definition\Type;
use Illuminate\Container\Container;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
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

    public function testDirectUpsertManyByIdentifyingColumnsMustNotBeEmpty(): void
    {
        $this->expectException(DefinitionException::class);
        $this->buildSchema(/** @lang GraphQL */ <<<'GRAPHQL'
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
            upsertUsers(inputs: [UpsertUserInput!]!): [User!]! @upsertMany(identifyingColumns: [])
        }
        GRAPHQL . self::PLACEHOLDER_QUERY);
    }

    public function testNestedUpsertManyByIdentifyingColumnsMustNotBeEmpty(): void
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
            tasks: [UpdateTaskInput!] @upsertMany(relation: "tasks", identifyingColumns: [])
        }

        input UpdateTaskInput {
            id: Int
            name: String
        }
        GRAPHQL . self::PLACEHOLDER_QUERY);
    }

    public function testNestedUpsertManyByIdentifyingColumn(): void
    {
        $user = factory(User::class)->create();
        $existingTask = factory(Task::class)->make();
        $existingTask->name = 'existing-task-many';
        $existingTask->difficulty = 1;
        $existingTask->user()->associate($user);
        $existingTask->save();

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

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            updateUser(input: {
                id: {$user->id}
                tasks: [
                    {
                        name: "existing-task-many"
                        difficulty: 2
                    }
                    {
                        name: "new-task-many"
                        difficulty: 3
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
                            'name' => 'existing-task-many',
                            'difficulty' => 2,
                        ],
                        [
                            'name' => 'new-task-many',
                            'difficulty' => 3,
                        ],
                    ],
                ],
            ],
        ]);

        $existingTask->refresh();

        $this->assertSame(2, $existingTask->difficulty);
        $this->assertSame(2, Task::count());
    }

    public function testNestedUpsertManyByIdentifyingColumnOnMultipleExistingModels(): void
    {
        $user = factory(User::class)->create();

        $firstExistingTask = factory(Task::class)->make();
        $firstExistingTask->name = 'first-existing-task';
        $firstExistingTask->difficulty = 1;
        $firstExistingTask->user()->associate($user);
        $firstExistingTask->save();

        $secondExistingTask = factory(Task::class)->make();
        $secondExistingTask->name = 'second-existing-task';
        $secondExistingTask->difficulty = 1;
        $secondExistingTask->user()->associate($user);
        $secondExistingTask->save();

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

        $this->graphQL(/** @lang GraphQL */ <<<GRAPHQL
        mutation {
            updateUser(input: {
                id: {$user->id}
                tasks: [
                    {
                        name: "first-existing-task"
                        difficulty: 2
                    }
                    {
                        name: "second-existing-task"
                        difficulty: 3
                    }
                ]
            }) {
                id
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'updateUser' => [
                    'id' => $user->id,
                ],
            ],
        ]);

        $firstExistingTask->refresh();
        $secondExistingTask->refresh();

        $this->assertSame(2, $firstExistingTask->difficulty);
        $this->assertSame(3, $secondExistingTask->difficulty);
        $this->assertSame(2, Task::count());
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

    public static function resolveType(): Type
    {
        $typeRegistry = Container::getInstance()->make(TypeRegistry::class);

        return $typeRegistry->get('Admin');
    }
}
