<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Company;
use Tests\Utils\Models\User;

final class FindDirectiveTest extends DBTestCase
{
    public function testReturnsSingleUser(): void
    {
        $this->createUserWithName('A');
        $userB = $this->createUserWithName('B');
        $this->createUserWithName('C');

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
        $userA = $this->createUserWithName('A');
        $this->createUserWithName('B');

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
        $this->createUserWithName('A');
        $this->createUserWithName('A');
        $this->createUserWithName('B');

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
        $companyA = $this->createCompanyWithName('CompanyA');
        $companyB = $this->createCompanyWithName('CompanyB');
        $userA = $this->createUserWithNameAndCompany('A', $companyA);
        $this->createUserWithNameAndCompany('A', $companyB);
        $this->createUserWithNameAndCompany('B', $companyA);

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
        $this->assertInstanceOf(Company::class, $company);
        $user = $this->createUserWithNameAndCompany('A', $company);

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

    private function createCompanyWithName(string $name): Company
    {
        $company = factory(Company::class)->make();
        $this->assertInstanceOf(Company::class, $company);
        $company->name = $name;
        $company->save();

        return $company;
    }

    private function createUserWithName(string $name): User
    {
        $user = factory(User::class)->make();
        $this->assertInstanceOf(User::class, $user);
        $user->name = $name;
        $user->save();

        return $user;
    }

    private function createUserWithNameAndCompany(string $name, Company $company): User
    {
        $user = factory(User::class)->make();
        $this->assertInstanceOf(User::class, $user);
        $user->name = $name;
        $user->company()->associate($company);
        $user->save();

        return $user;
    }
}
