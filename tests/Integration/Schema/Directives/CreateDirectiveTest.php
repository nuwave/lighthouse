<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Tests\Constants;
use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

final class CreateDirectiveTest extends DBTestCase
{
    public function testCreateFromFieldArguments(): void
    {
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Company {
            id: ID!
            name: String!
        }

        type Mutation {
            createCompany(name: String): Company @create
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            createCompany(name: "foo") {
                id
                name
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'createCompany' => [
                    'id' => '1',
                    'name' => 'foo',
                ],
            ],
        ]);
    }

    public function testCreateFromInputObject(): void
    {
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Company {
            id: ID!
            name: String!
        }

        type Mutation {
            createCompany(input: CreateCompanyInput! @spread): Company @create
        }

        input CreateCompanyInput {
            name: String
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            createCompany(input: {
                name: "foo"
            }) {
                id
                name
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'createCompany' => [
                    'id' => '1',
                    'name' => 'foo',
                ],
            ],
        ]);
    }

    public function testCreatesAnEntryWithDatabaseDefaultsAndReturnsItImmediately(): void
    {
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Mutation {
            createTag(name: String): Tag @create
        }

        type Tag {
            name: String!
            default_string: String!
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            createTag(name: "foobar") {
                name
                default_string
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'createTag' => [
                    'name' => 'foobar',
                    'default_string' => Constants::TAGS_DEFAULT_STRING,
                ],
            ],
        ]);
    }

    public function testDoesNotCreateWithFailingRelationship(): void
    {
        $task = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $task);
        $task->name = 'Uniq';
        $task->save();

        $config = $this->app->make(ConfigRepository::class);
        $config->set('app.debug', false);

        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Task {
            id: ID!
            name: String!
        }

        type User {
            id: ID!
            name: String
            tasks: [Task!]! @hasMany
        }

        type Mutation {
            createUser(input: CreateUserInput! @spread): User @create
        }

        input CreateUserInput {
            name: String
            tasks: CreateTaskRelation
        }

        input CreateTaskRelation {
            create: [CreateTaskInput!]
        }

        input CreateTaskInput {
            name: String
            user: ID
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            createUser(input: {
                name: "foo"
                tasks: {
                    create: [{
                        name: "Uniq"
                    }]
                }
            }) {
                id
                name
                tasks {
                    id
                    name
                }
            }
        }
        GRAPHQL)
            ->assertJson([
                'data' => [
                    'createUser' => null,
                ],
            ])
            ->assertJsonCount(1, 'errors');

        $this->assertCount(0, User::all());
    }

    public function testCreatesOnPartialFailureWithTransactionsDisabled(): void
    {
        $task = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $task);
        $task->name = 'Uniq';
        $task->save();

        $config = $this->app->make(ConfigRepository::class);
        $config->set('app.debug', false);
        $config->set('lighthouse.transactional_mutations', false);

        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Task {
            id: ID!
            name: String!
        }

        type User {
            id: ID!
            name: String
            tasks: [Task!]! @hasMany
        }

        type Mutation {
            createUser(input: CreateUserInput! @spread): User @create
        }

        input CreateUserInput {
            name: String
            tasks: CreateTaskRelation
        }

        input CreateTaskRelation {
            create: [CreateTaskInput!]
        }

        input CreateTaskInput {
            name: String
            user: ID
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            createUser(input: {
                name: "foo"
                tasks: {
                    create: [{
                        name: "Uniq"
                    }]
                }
            }) {
                id
                name
                tasks {
                    id
                    name
                }
            }
        }
        GRAPHQL)
            // TODO allow partial success
//            ->assertJson([
//                'data' => [
//                    'createUser' => [
//                        'name' => 'foo',
//                        'tasks' => null,
//                    ],
//                ],
//            ])
            ->assertJsonCount(1, 'errors');

        $this->assertCount(1, User::all());
    }

    public function testDoesNotFailWhenPropertyNameMatchesModelsNativeMethods(): void
    {
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Task {
            id: ID!
            name: String!
            guard: String
        }

        type User {
            id: ID!
            name: String
            tasks: [Task!]! @hasMany
        }

        type Mutation {
            createUser(input: CreateUserInput! @spread): User @create
        }

        input CreateUserInput {
            name: String
            tasks: CreateTaskRelation
        }

        input CreateTaskRelation {
            create: [CreateTaskInput!]
        }

        input CreateTaskInput {
            name: String
            guard: String
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            createUser(input: {
                name: "foo"
                tasks: {
                    create: [{
                        name: "Uniq"
                        guard: "api"
                    }]
                }
            }) {
                tasks {
                    guard
                }
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'createUser' => [
                    'tasks' => [
                        [
                            'guard' => 'api',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testNestedArgResolverHasMany(): void
    {
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Mutation {
            createUser(input: CreateUserInput! @spread): User @create
        }

        type Task {
            name: String!
        }

        type User {
            name: String
            tasks: [Task!]! @hasMany
        }

        input CreateUserInput {
            name: String
            newTask: CreateTaskInput @create(relation: "tasks")
        }

        input CreateTaskInput {
            name: String
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            createUser(input: {
                name: "foo"
                newTask: {
                    name: "Uniq"
                }
            }) {
                name
                tasks {
                    name
                }
            }
        }
        GRAPHQL)->assertExactJson([
            'data' => [
                'createUser' => [
                    'name' => 'foo',
                    'tasks' => [
                        [
                            'name' => 'Uniq',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testNestedArgResolverForOptionalBelongsTo(): void
    {
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Mutation {
            createTask(input: CreateTaskInput! @spread): Task @create
        }

        type Task {
            name: String!
            user: User @belongsTo
        }

        type User {
            name: String
        }

        input CreateTaskInput {
            name: String
            user: CreateUserInput @create
        }

        input CreateUserInput {
            name: String
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            createTask(input: {
                name: "task"
                user: {
                    name: "user"
                }
            }) {
                name
                user {
                    name
                }
            }
        }
        GRAPHQL)->assertExactJson([
            'data' => [
                'createTask' => [
                    'name' => 'task',
                    'user' => [
                        'name' => 'user',
                    ],
                ],
            ],
        ]);
    }

    public function testCreateTwice(): void
    {
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Task {
            id: ID!
            name: String!
        }

        type User {
            id: ID!
            name: String
            tasks: [Task!]! @hasMany
        }

        type Mutation {
            createUser(input: CreateUserInput! @spread): User @create
        }

        input CreateUserInput {
            name: String
            tasks: CreateTaskRelation
        }

        input CreateTaskRelation {
            create: [CreateTaskInput!]
        }

        input CreateTaskInput {
            name: String
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            createUser(input: {
                name: "foo"
                tasks: {
                    create: [{
                        name: "fooTask"
                    }]
                }
            }) {
                name
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'createUser' => [
                    'name' => 'foo',
                ],
            ],
        ]);

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            createUser(input: {
                name: "bar"
                tasks: {
                    create: [{
                        name: "barTask"
                    }]
                }
            }) {
                name
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'createUser' => [
                    'name' => 'bar',
                ],
            ],
        ]);
    }

    public function testCreateTwiceWithCreateDirective(): void
    {
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Task {
            id: ID!
            name: String!
        }

        type User {
            id: ID!
            name: String
            tasks: [Task!]! @hasMany
        }

        type Mutation {
            createUser(input: CreateUserInput! @spread): User @create
        }

        input CreateUserInput {
            name: String
            tasks: [CreateTaskInput!] @create
        }

        input CreateTaskInput {
            name: String
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            createUser(input: {
                name: "foo"
                tasks: [
                    {
                        name: "fooTask"
                    },
                    {
                        name: "barTask"
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
                'createUser' => [
                    'name' => 'foo',
                    'tasks' => [
                        [
                            'name' => 'fooTask',
                        ],
                        [
                            'name' => 'barTask',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testTurnOnMassAssignment(): void
    {
        config(['lighthouse.force_fill' => false]);

        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Company {
            name: String!
        }

        type Mutation {
            createCompany(name: String): Company @create
        }
        GRAPHQL;

        $this->expectException(MassAssignmentException::class);

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            createCompany(name: "foo") {
                name
            }
        }
        GRAPHQL);
    }
}
