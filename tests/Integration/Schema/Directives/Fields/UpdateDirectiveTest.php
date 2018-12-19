<?php

namespace Tests\Integration\Schema\Directives\Fields;

use Tests\DBTestCase;
use Illuminate\Support\Arr;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;
use Tests\Utils\Models\Company;
use Tests\Utils\Models\Category;

class UpdateDirectiveTest extends DBTestCase
{
    /**
     * @test
     */
    public function itCanUpdateFromFieldArguments()
    {
        factory(Company::class)->create(['name' => 'foo']);

        $schema = '
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
        $query = '
        mutation {
            updateCompany(
                id: 1
                name: "bar"
            ) {
                id
                name
            }
        }
        ';
        $result = $this->execute($schema, $query);

        $this->assertSame('1', Arr::get($result, 'data.updateCompany.id'));
        $this->assertSame('bar', Arr::get($result, 'data.updateCompany.name'));
        $this->assertSame('bar', Company::first()->name);
    }

    /**
     * @test
     */
    public function itCanUpdateFromInputObject()
    {
        factory(Company::class)->create(['name' => 'foo']);

        $schema = '
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
        $query = '
        mutation {
            updateCompany(input: {
                id: 1
                name: "bar"
            }) {
                id
                name
            }
        }
        ';
        $result = $this->execute($schema, $query);

        $this->assertSame('1', Arr::get($result, 'data.updateCompany.id'));
        $this->assertSame('bar', Arr::get($result, 'data.updateCompany.name'));
        $this->assertSame('bar', Company::first()->name);
    }

    /**
     * @test
     */
    public function itCanUpdateWithBelongsTo()
    {
        factory(User::class, 2)->create();
        factory(Task::class)->create([
            'name' => 'bar',
            'user_id' => 1,
        ]);

        $schema = '
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
            user: ID
        }
        '.$this->placeholderQuery();
        $query = '
        mutation {
            updateTask(input: {
                id: 1
                name: "foo"
                user: 2
            }) {
                id
                name
                user {
                    id
                }
            }
        }
        ';
        $result = $this->execute($schema, $query);

        $this->assertSame('1', Arr::get($result, 'data.updateTask.id'));
        $this->assertSame('foo', Arr::get($result, 'data.updateTask.name'));
        $this->assertSame('2', Arr::get($result, 'data.updateTask.user.id'));

        $task = Task::first();
        $this->assertSame('2', $task->user_id);
        $this->assertSame('foo', $task->name);
    }

    /**
     * @test
     */
    public function itCanUpdateWithCustomPrimaryKey()
    {
        factory(Category::class)->create(['name' => 'foo']);

        $schema = '
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
        $query = '
        mutation {
            updateCategory(
                category_id: 1
                name: "bar"
            ) {
                category_id
                name
            }
        }
        ';
        $result = $this->execute($schema, $query);

        $this->assertSame('1', Arr::get($result, 'data.updateCategory.category_id'));
        $this->assertSame('bar', Arr::get($result, 'data.updateCategory.name'));
        $this->assertSame('bar', Category::first()->name);
    }

    /**
     * @test
     */
    public function itDoesNotUpdateWithFailingRelationship()
    {
        factory(User::class)->create(['name' => 'Original']);

        $schema = '
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
        $query = '
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
        ';
        $result = $this->execute($schema, $query);

        $this->assertEquals('Original', User::first()->name);
        $this->assertTrue(Arr::has($result, 'errors'));
    }
}
