<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\User;
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

        $this->graphQL('
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
                input: UpdateCompanyInput @spread
            ): Company @update
        }
        
        input UpdateCompanyInput {
            id: ID!
            name: String
        }
        '.$this->placeholderQuery();

        $this->graphQL('
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

        $this->graphQL('
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
            name: String
            user: ID
        }
        '.$this->placeholderQuery();

        $this->graphQL('
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
