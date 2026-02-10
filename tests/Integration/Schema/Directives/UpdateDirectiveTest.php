<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Illuminate\Database\QueryException;
use Nuwave\Lighthouse\Execution\Arguments\UpdateModel;
use Tests\DBTestCase;
use Tests\Utils\Models\Category;
use Tests\Utils\Models\Company;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

final class UpdateDirectiveTest extends DBTestCase
{
    public function testUpdateFromFieldArguments(): void
    {
        $company = factory(Company::class)->make();
        $this->assertInstanceOf(Company::class, $company);
        $company->name = 'foo';
        $company->save();

        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Company {
            id: ID!
            name: String!
        }

        type Mutation {
            updateCompany(
                id: ID!
                name: String
            ): Company @update
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            updateCompany(
                id: 1
                name: "bar"
            ) {
                id
                name
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'updateCompany' => [
                    'id' => '1',
                    'name' => 'bar',
                ],
            ],
        ]);

        $this->assertSame('bar', Company::firstOrFail()->name);
    }

    public function testUpdateFromInputObject(): void
    {
        $company = factory(Company::class)->make();
        $this->assertInstanceOf(Company::class, $company);
        $company->name = 'foo';
        $company->save();

        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Company {
            id: ID!
            name: String!
        }

        type Mutation {
            updateCompany(
                input: UpdateCompanyInput @spread
            ): Company @update
        }

        input UpdateCompanyInput {
            id: ID!
            name: String
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            updateCompany(input: {
                id: 1
                name: "bar"
            }) {
                id
                name
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'updateCompany' => [
                    'id' => '1',
                    'name' => 'bar',
                ],
            ],
        ]);

        $this->assertSame('bar', Company::firstOrFail()->name);
    }

    public function testThrowsWhenMissingPrimaryKey(): void
    {
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Company {
            id: ID!
        }

        type Mutation {
            updateCompany: Company @update
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            updateCompany {
                id
            }
        }
        GRAPHQL)->assertGraphQLErrorMessage(UpdateModel::MISSING_PRIMARY_KEY_FOR_UPDATE);
    }

    public function testUpdateWithCustomPrimaryKey(): void
    {
        $category = factory(Category::class)->make();
        $this->assertInstanceOf(Category::class, $category);
        $category->name = 'foo';
        $category->save();

        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Category {
            category_id: ID!
            name: String!
        }

        type Mutation {
            updateCategory(
                category_id: ID!
                name: String
            ): Category @update
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            updateCategory(
                category_id: 1
                name: "bar"
            ) {
                category_id
                name
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'updateCategory' => [
                    'category_id' => '1',
                    'name' => 'bar',
                ],
            ],
        ]);

        $this->assertSame('bar', Category::firstOrFail()->name);
    }

    public function testUpdateWithCustomPrimaryKeyAsId(): void
    {
        $category = factory(Category::class)->make();
        $this->assertInstanceOf(Category::class, $category);
        $category->name = 'foo';
        $category->save();

        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Category {
            id: ID! @rename(attribute: "category_id")
            name: String!
        }

        type Mutation {
            updateCategory(
                id: ID!
                name: String
            ): Category @update
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            updateCategory(
                id: 1
                name: "bar"
            ) {
                id
                name
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'updateCategory' => [
                    'id' => '1',
                    'name' => 'bar',
                ],
            ],
        ]);

        $this->assertSame('bar', Category::firstOrFail()->name);
    }

    public function testDoesNotUpdateWithFailingRelationship(): void
    {
        $user = factory(User::class)->make();
        $this->assertInstanceOf(User::class, $user);
        $user->name = 'Original';
        $user->save();

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
            updateUser(input: UpdateUserInput! @spread): User @update
        }

        input UpdateUserInput {
            id: ID!
            name: String
            tasks: CreateTaskRelation
        }

        input CreateTaskRelation {
            create: [CreateTaskInput!]
        }

        input CreateTaskInput {
            thisFieldDoesNotExist: String
        }
        GRAPHQL;

        $this->expectException(QueryException::class);
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            updateUser(input: {
                id: 1
                name: "Changed"
                tasks: {
                    create: [{
                        thisFieldDoesNotExist: "bar"
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
        GRAPHQL);

        $this->assertSame('Original', User::firstOrFail()->name);
    }

    public function testNestedArgResolver(): void
    {
        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $task = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $task);
        $task->id = 3;
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
            updateTask: UpdateTaskInput @update(relation: "tasks")
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
                updateTask: {
                    id: 3
                    name: "Uniq"
                }
            }) {
                name
                tasks {
                    id
                    name
                }
            }
        }
        GRAPHQL)->assertExactJson([
            'data' => [
                'updateUser' => [
                    'name' => 'foo',
                    'tasks' => [
                        [
                            'id' => 3,
                            'name' => 'Uniq',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testNestedUpdateOnInputList(): void
    {
        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $taskA = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $taskA);
        $taskA->id = 3;
        $taskA->save();

        $taskB = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $taskB);
        $taskB->id = 4;
        $taskB->save();

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
            updateTask: [UpdateTaskInput] @update(relation: "tasks")
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
                updateTask: [
                    {
                        id: 3
                        name: "Uniq"
                    },
                    {
                        id: 4,
                        name: "Foo"
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
                            'id' => 3,
                            'name' => 'Uniq',
                        ], [
                            'id' => 4,
                            'name' => 'Foo',
                        ],
                    ],
                ],
            ],
        ]);
    }
}
