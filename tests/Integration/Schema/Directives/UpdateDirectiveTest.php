<?php

namespace Tests\Integration\Schema\Directives;

use Illuminate\Database\QueryException;
use Nuwave\Lighthouse\Execution\Arguments\UpdateModel;
use Tests\DBTestCase;
use Tests\Utils\Models\Category;
use Tests\Utils\Models\Company;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class UpdateDirectiveTest extends DBTestCase
{
    public function testCanUpdateFromFieldArguments(): void
    {
        factory(Company::class)->create(['name' => 'foo']);

        $this->schema .= /** @lang GraphQL */ '
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
        ';

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            updateCompany(
                id: 1
                name: "bar"
            ) {
                id
                name
            }
        }
        ')->assertJson([
            'data' => [
                'updateCompany' => [
                    'id' => '1',
                    'name' => 'bar',
                ],
            ],
        ]);

        $this->assertSame('bar', Company::firstOrFail()->name);
    }

    public function testCanUpdateFromInputObject(): void
    {
        factory(Company::class)->create(['name' => 'foo']);

        $this->schema .= /** @lang GraphQL */ '
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
        ';

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            updateCompany(input: {
                id: 1
                name: "bar"
            }) {
                id
                name
            }
        }
        ')->assertJson([
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
        $this->schema .= /** @lang GraphQL */ '
        type Company {
            id: ID!
        }

        type Mutation {
            updateCompany: Company @update
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            updateCompany {
                id
            }
        }
        ')->assertJson([
            'errors' => [
                [
                    'message' => UpdateModel::MISSING_PRIMARY_KEY_FOR_UPDATE,
                ],
            ],
        ]);
    }

    public function testCanUpdateWithCustomPrimaryKey(): void
    {
        factory(Category::class)->create(['name' => 'foo']);

        $this->schema .= /** @lang GraphQL */ '
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
        ';

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            updateCategory(
                category_id: 1
                name: "bar"
            ) {
                category_id
                name
            }
        }
        ')->assertJson([
            'data' => [
                'updateCategory' => [
                    'category_id' => '1',
                    'name' => 'bar',
                ],
            ],
        ]);

        $this->assertSame('bar', Category::firstOrFail()->name);
    }

    public function testCanUpdateWithCustomPrimaryKeyAsId(): void
    {
        factory(Category::class)->create(['name' => 'foo']);

        $this->schema .= /** @lang GraphQL */ '
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
        ';

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            updateCategory(
                id: 1
                name: "bar"
            ) {
                id
                name
            }
        }
        ')->assertJson([
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
        factory(User::class)->create(['name' => 'Original']);

        $this->schema .= /** @lang GraphQL */ '
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
        ';

        $this->expectException(QueryException::class);
        $this->graphQL(/** @lang GraphQL */ '
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
        ');

        $this->assertSame('Original', User::firstOrFail()->name);
    }

    public function testNestedArgResolver(): void
    {
        factory(User::class)->create();
        factory(Task::class)->create([
            'id' => 3,
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
            updateTask: UpdateTaskInput @update(relation: "tasks")
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
        ')->assertExactJson([
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
        factory(User::class)->create();
        factory(Task::class)->create([
            'id' => 3,
        ]);
        factory(Task::class)->create([
            'id' => 4,
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
            updateTask: [UpdateTaskInput] @update(relation: "tasks")
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
        ')->assertExactJson([
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
