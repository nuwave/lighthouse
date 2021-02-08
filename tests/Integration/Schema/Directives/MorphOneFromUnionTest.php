<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Color;
use Tests\Utils\Models\Company;
use Tests\Utils\Models\Contractor;
use Tests\Utils\Models\Employee;
use Tests\Utils\Models\Team;
use Tests\Utils\Models\User;

class MorphOneFromUnionTest extends DBTestCase
{
    public function testCanResolveMorphOneRelationshipOnInterface(): void
    {
        /** @var \Tests\Utils\Models\Employee $employee */
        $employee = factory(Employee::class)->create();
        /** @var \Tests\Utils\Models\Contractor $contractor */
        $contractor = factory(Contractor::class)->create();

        $companyId = factory(Company::class)->create()->getKey();
        $teamId = factory(Team::class)->create()->getKey();

        /** @var \Tests\Utils\Models\User $employeeUser */
        $employeeUser = factory(User::class)->create([
            'company_id' => $companyId,
            'team_id' => $teamId,
        ]);
        /** @var \Tests\Utils\Models\User $contractorUser */
        $contractorUser = factory(User::class)->create([
            'company_id' => $companyId,
            'team_id' => $teamId,
        ]);

        $employee->user()->save($employeeUser);
        $contractor->user()->save($contractorUser);

        /** @var \Tests\Utils\Models\Color $employeeColor */
        $employeeColor = factory(Color::class)->create();
        /** @var \Tests\Utils\Models\Color $contractorColor */
        $contractorColor = factory(Color::class)->create();

        $employee->colors()->save($employeeColor);
        $contractor->colors()->save($contractorColor);

        $this->schema = /** @lang GraphQL */ '
        interface Person {
            id: ID!
            user: User! @morphOne
        }

        union Creator = Employee | Contractor

        type User {
            id: ID!
            name: String!
            email: String!
        }

        type Employee implements Person {
            id: ID!
            position: String!
            user: User! @morphOne
        }

        type Contractor implements Person {
            id: ID!
            position: String!
            user: User! @morphOne
        }

        type Color {
            id: ID!
            name: String!
            creator: Creator @morphTo
        }

        type Query {
            colors: [Color!]! @all
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            colors {
                id
                name
                creator {
                    ... on Person {
                        __typename
                        id
                        user {
                            id
                            name
                            email
                        }
                    }
                }
            }
        }
        ')->assertJson([
            'data' => [
                'colors' => [
                    [
                        'id' => (string) $employeeColor->id,
                        'name' => $employeeColor->name,
                        'creator' => [
                            '__typename' => 'Employee',
                            'id' => (string) $employee->id,
                            'user' => [
                                'id' => (string) $employeeUser->id,
                                'name' => $employeeUser->name,
                                'email' => $employeeUser->email,
                            ],
                        ],
                    ],
                    [
                        'id' => (string) $contractorColor->id,
                        'name' => $contractorColor->name,
                        'creator' => [
                            '__typename' => 'Contractor',
                            'id' => (string) $contractor->id,
                            'user' => [
                                'id' => (string) $contractorUser->id,
                                'name' => $contractorUser->name,
                                'email' => $contractorUser->email,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
