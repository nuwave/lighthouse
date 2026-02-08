<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Color;
use Tests\Utils\Models\Company;
use Tests\Utils\Models\Contractor;
use Tests\Utils\Models\Employee;
use Tests\Utils\Models\Team;
use Tests\Utils\Models\User;

final class MorphOneFromUnionTest extends DBTestCase
{
    public function testResolveMorphOneRelationshipOnInterface(): void
    {
        /** @var Employee $employee */
        $employee = factory(Employee::class)->create();
        /** @var Contractor $contractor */
        $contractor = factory(Contractor::class)->create();

        $company = factory(Company::class)->create();
        $this->assertInstanceOf(Company::class, $company);
        $team = factory(Team::class)->create();
        $this->assertInstanceOf(Team::class, $team);

        $employeeUser = factory(User::class)->make();
        $this->assertInstanceOf(User::class, $employeeUser);
        $employeeUser->company()->associate($company);
        $employeeUser->team()->associate($team);
        $employeeUser->save();
        $contractorUser = factory(User::class)->make();
        $this->assertInstanceOf(User::class, $contractorUser);
        $contractorUser->company()->associate($company);
        $contractorUser->team()->associate($team);
        $contractorUser->save();

        $employee->user()->save($employeeUser);
        $contractor->user()->save($contractorUser);

        /** @var Color $employeeColor */
        $employeeColor = factory(Color::class)->create();
        /** @var Color $contractorColor */
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
