<?php

namespace Tests\Integration\Schema\Directives\Fields;

use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;
use Tests\Utils\Models\Project;
use Tests\Utils\Models\Company;
use Tests\Utils\Models\Category;

class UpdateDirectiveTest extends DBTestCase
{
    /**
     * @test
     */
    public function itCanUpdateFromFieldArguments(): void
    {
        factory(Company::class)->create(['name' => 'foo']);

        $this->schema = '
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
        '.$this->placeholderQuery();

        $this->query('
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
        $this->assertSame('bar', Company::first()->name);
    }

    /**
     * @test
     */
    public function itCanUpdateFromInputObject(): void
    {
        factory(Company::class)->create(['name' => 'foo']);

        $this->schema = '
        type Company {
            id: ID!
            name: String!
        }
        
        type Mutation {
            updateCompany(
                input: UpdateCompanyInput
            ): Company @update(flatten: true)
        }
        
        input UpdateCompanyInput {
            id: ID!
            name: String
        }
        '.$this->placeholderQuery();

        $this->query('
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
        $this->assertSame('bar', Company::first()->name);
    }

    /**
     * @test
     */
    public function itCanUpdateWithBelongsTo(): void
    {
        factory(User::class, 2)->create();
        factory(Task::class)->create([
            'name' => 'bar',
            'user_id' => 1,
        ]);

        $this->schema = '
        type Task {
            id: ID!
            name: String!
            user: User @belongsTo
        }
        
        type User {
            id: ID
        }
        
        type Mutation {
            updateTask(input: UpdateTaskInput!): Task @update(flatten: true)
        }
        
        input UpdateTaskInput {
            id: ID!
            name: String
            user_id: ID
        }
        '.$this->placeholderQuery();

        $this->query('
        mutation {
            updateTask(input: {
                id: 1
                name: "foo"
                user_id: 2
            }) {
                id
                name
                user {
                    id
                }
            }
        }
        ')->assertJson([
            'data' => [
                'updateTask' => [
                    'id' => '1',
                    'name' => 'foo',
                    'user' => [
                        'id' => '2',
                    ],
                ],
            ],
        ]);

        $task = Task::first();
        $this->assertSame('2', $task->user_id);
        $this->assertSame('foo', $task->name);
    }

    /**
     * @test
     */
    public function itCanUpdateWithCustomPrimaryKey(): void
    {
        factory(Category::class)->create(['name' => 'foo']);

        $this->schema = '
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
        '.$this->placeholderQuery();

        $this->query('
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
        $this->assertSame('bar', Category::first()->name);
    }

    /**
     * @test
     */
    public function itCanUpdateWithCustomLighthouseKey(): void
    {
        factory(Project::class)->create(['title' => 'foo', 'uuid' => '679b592c-1d77-4994-80b5-6b5cb5c5930f']);

        $this->schema = '
        type Project {
            id: String! @rename(attribute: "uuid")
            title: String!
        }
        
        type Mutation {
            updateProject(
                id: String!
                title: String
            ): Project @update
        }
        '.$this->placeholderQuery();

        $this->query('
        mutation {
            updateProject(
                id: "679b592c-1d77-4994-80b5-6b5cb5c5930f"
                title: "bar"
            ) {
                id
                title
            }
        }
        ')->assertJson([
            'data' => [
                'updateProject' => [
                    'id' => '679b592c-1d77-4994-80b5-6b5cb5c5930f',
                    'title' => 'bar',
                ],
            ],
        ]);
        $this->assertSame('bar', Project::first()->title);
    }

    /**
     * @test
     */
    public function itDoesNotUpdateWithFailingRelationship(): void
    {
        factory(User::class)->create(['name' => 'Original']);

        $this->schema = '
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
            updateUser(input: UpdateUserInput!): User @update(flatten: true)
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
            name: String
            user: ID
        }
        '.$this->placeholderQuery();

        $this->query('
        mutation {
            updateUser(input: {
                id: 1
                name: "Changed"
                tasks: {
                    corruptField: [{
                        name: "bar"
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
        ')->assertJsonCount(1, 'errors');
        $this->assertSame('Original', User::first()->name);
    }
}
