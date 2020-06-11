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
    /** @var \Tests\Utils\Models\Employee */
    protected $employee;

    /** @var \Tests\Utils\Models\Contractor */
    protected $contractor;

    /** @var \Tests\Utils\Models\Color */
    protected $color1;

    /** @var \Tests\Utils\Models\Color */
    protected $color2;

    protected function setUp(): void
    {
        parent::setUp();

        $companyId = factory(Company::class)->create()->getKey();
        $teamId = factory(Team::class)->create()->getKey();

        $user1 = factory(User::class)->create([
            'company_id' => $companyId,
            'team_id' => $teamId,
        ]);
        $user2 = factory(User::class)->create([
            'company_id' => $companyId,
            'team_id' => $teamId,
        ]);

        $this->employee = factory(Employee::class)->create();
        $this->contractor = factory(Contractor::class)->create();
        $this->employee->user()->save($user1);
        $this->contractor->user()->save($user2);

        $this->color1 = factory(Color::class)->create();
        $this->color2 = factory(Color::class)->create();

        $this->employee->colors()->save($this->color1);
        $this->contractor->colors()->save($this->color2);
    }

    public function testCanResolveMorphOneRelationshipOnInterface(): void
    {
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
                        'id' => (string) $this->color1->id,
                        'name' => $this->color1->name,
                        'creator' => [
                            '__typename' => 'Employee',
                            'id' => (string) $this->employee->id,
                            'user' => [
                                'id' => (string) $this->employee->user->id,
                                'name' => $this->employee->user->name,
                                'email' => $this->employee->user->email,
                            ],
                        ],
                    ],
                    [
                        'id' => (string) $this->color2->id,
                        'name' => $this->color2->name,
                        'creator' => [
                            '__typename' => 'Contractor',
                            'id' => (string) $this->contractor->id,
                            'user' => [
                                'id' => (string) $this->contractor->user->id,
                                'name' => $this->contractor->user->name,
                                'email' => $this->contractor->user->email,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
