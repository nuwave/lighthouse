<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Company;
use Tests\Utils\Models\User;

class FindDirectiveTest extends DBTestCase
{
    public function testReturnsSingleUser(): void
    {
        factory(User::class)->create(['name' => 'A']);
        $userB = factory(User::class)->create(['name' => 'B']);
        factory(User::class)->create(['name' => 'C']);

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String!
        }

        type Query {
            user(id: ID @eq): User @find(model: "User")
        }
        ';

        $this->graphQL(/** @lang GraphQL */ "
        {
            user(id:{$userB->id}) {
                name
            }
        }
        ")->assertJsonFragment([
            'user' => [
                'name' => 'B',
            ],
        ]);
    }

    public function testDefaultsToFieldTypeIfNoModelIsSupplied(): void
    {
        $userA = factory(User::class)->create(['name' => 'A']);
        factory(User::class)->create(['name' => 'B']);

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String!
        }

        type Query {
            user(id: ID @eq): User @find
        }
        ';

        $this->graphQL(/** @lang GraphQL */ "
        {
            user(id:{$userA->id}) {
                name
            }
        }
        ")->assertJsonFragment([
            'name' => 'A',
        ]);
    }

    public function testCannotFetchIfMultipleModelsMatch(): void
    {
        factory(User::class)->create(['name' => 'A']);
        factory(User::class)->create(['name' => 'A']);
        factory(User::class)->create(['name' => 'B']);

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String!
        }

        type Query {
            user(name: String @eq): User @find(model: "User")
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user(name: "A") {
                name
            }
        }
        ')->assertJsonCount(1, 'errors');
    }

    public function testUseScopes(): void
    {
        $companyA = factory(Company::class)->create(['name' => 'CompanyA']);
        $companyB = factory(Company::class)->create(['name' => 'CompanyB']);
        $userA = factory(User::class)->create(['name' => 'A', 'company_id' => $companyA->id]);
        factory(User::class)->create(['name' => 'A', 'company_id' => $companyB->id]);
        factory(User::class)->create(['name' => 'B', 'company_id' => $companyA->id]);

        $this->schema = /** @lang GraphQL */ '
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

        $this->graphQL(/** @lang GraphQL */ '
        {
            user(name: "A" company: "CompanyA") {
                id
                name
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'id' => $userA->id,
                    'name' => 'A',
                ],
            ],
        ]);
    }

    public function testReturnsAnEmptyObjectWhenTheModelIsNotFound(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String!
        }

        type Query {
            user(name: String @eq): User @find(model: "User")
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user(name: "A") {
                id
                name
            }
        }
        ')->assertExactJson([
            'data' => [
                'user' => null,
            ],
        ]);
    }

    public function testReturnsCustomAttributes(): void
    {
        $company = factory(Company::class)->create();
        $user = factory(User::class)->create([
            'name' => 'A',
            'company_id' => $company->id,
        ]);

        $this->schema = '
        type User {
            id: ID!
            name: String!
            companyName: String!
        }

        type Query {
            user(id: ID @eq): User @find(model: "User")
        }
        ';

        $this->graphQL("
        {
            user(id: {$user->id}) {
                id
                name
                companyName
            }
        }
        ")->assertJson([
            'data' => [
                'user' => [
                    'id' => (string) $user->id,
                    'name' => $user->name,
                    'companyName' => $company->name,
                ],
            ],
        ]);
    }
}
