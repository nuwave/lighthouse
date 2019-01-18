<?php

namespace Tests\Integration\Schema\Directives\Fields;

use Tests\DBTestCase;
use Tests\Utils\Models\User;
use Tests\Utils\Models\Company;

class FindDirectiveTest extends DBTestCase
{
    /** @test */
    public function itReturnsSingleUser()
    {
        $userA = factory(User::class)->create(['name' => 'A']);
        $userB = factory(User::class)->create(['name' => 'B']);
        $userC = factory(User::class)->create(['name' => 'C']);

        $schema = '
        type User {
            id: ID!
            name: String!
        }
        
        type Query {
            user(id: ID @eq): User @find(model: "User")
        }
        ';
        $query = "
        {
            user(id:{$userB->id}) {
                name
            }
        }
        ";
        $result = $this->executeQuery($schema, $query);

        $this->assertEquals('B', $result->data['user']['name']);
    }

    /** @test */
    public function itDefaultsToFieldTypeIfNoModelIsSupplied()
    {
        $userA = factory(User::class)->create(['name' => 'A']);
        $userB = factory(User::class)->create(['name' => 'B']);

        $schema = '
        type User {
            id: ID!
            name: String!
        }
        
        type Query {
            user(id: ID @eq): User @find
        }
        ';
        $query = "
        {
            user(id:{$userA->id}) {
                name
            }
        }
        ";

        $result = $this->executeQuery($schema, $query);

        $this->assertEquals('A', $result->data['user']['name']);
    }

    /** @test */
    public function itCannotFetchIfMultipleModelsMatch()
    {
        $userA = factory(User::class)->create(['name' => 'A']);
        $userB = factory(User::class)->create(['name' => 'A']);
        $userC = factory(User::class)->create(['name' => 'B']);

        $schema = '
        type User {
            id: ID!
            name: String!
        }
        
        type Query {
            user(name: String @eq): User @find(model: "User")
        }
        ';
        $query = '
        {
            user(name: "A") {
                name
            }
        }
        ';
        $result = $this->executeQuery($schema, $query);

        $this->assertCount(1, $result->errors);
    }

    /** @test */
    public function itCanUseScopes()
    {
        $companyA = factory(Company::class)->create(['name' => 'CompanyA']);
        $companyB = factory(Company::class)->create(['name' => 'CompanyB']);
        $userA = factory(User::class)->create(['name' => 'A', 'company_id' => $companyA->id]);
        $userB = factory(User::class)->create(['name' => 'A', 'company_id' => $companyB->id]);
        $userC = factory(User::class)->create(['name' => 'B', 'company_id' => $companyA->id]);

        $schema = '
        type Company {
            name: String!
        }
        
        type User {
            id: ID!
            name: String!
        }
        
        type Query {
            user(name: String @eq, company: String!): User @find(model: "User" scopes: [companyName])
        }
        ';
        $query = '
        {
            user(name: "A" company: "CompanyA") {
                id
                name
            }
        }
        ';
        $result = $this->executeQuery($schema, $query);

        $this->assertEquals($userA->id, $result->data['user']['id']);
        $this->assertEquals('A', $result->data['user']['name']);
    }
}
