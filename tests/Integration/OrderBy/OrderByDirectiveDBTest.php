<?php

namespace Tests\Integration\OrderBy;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

class OrderByDirectiveDBTest extends DBTestCase
{
    protected $schema = /** @lang GraphQL */ '
    type Query {
        users(
            orderBy: _ @orderBy
            orderByRestricted: _ @orderBy(columns: ["name"])
        ): [User!]! @all
    }

    type User {
        name: String
        team_id: Int
    }
    ';

    public function testCanOrderByTheGivenColumnAndSortOrderASC(): void
    {
        factory(User::class)->create(['name' => 'B']);
        factory(User::class)->create(['name' => 'A']);

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                orderBy: [
                    {
                        field: "name"
                        order: ASC
                    }
                ]
            ) {
                name
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    [
                        'name' => 'A',
                    ],
                    [
                        'name' => 'B',
                    ],
                ],
            ],
        ]);
    }

    public function testCanOrderByTheGivenFieldAndSortOrderDESC(): void
    {
        factory(User::class)->create(['name' => 'B']);
        factory(User::class)->create(['name' => 'A']);

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                orderBy: [
                    {
                        field: "name"
                        order: ASC
                    }
                ]
            ) {
                name
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    [
                        'name' => 'B',
                    ],
                    [
                        'name' => 'A',
                    ],
                ],
            ],
        ]);
    }

    public function testCanOrderByMultipleColumns(): void
    {
        factory(User::class)->create(['name' => 'B', 'team_id' => 2]);
        factory(User::class)->create(['name' => 'A', 'team_id' => 5]);
        factory(User::class)->create(['name' => 'C', 'team_id' => 2]);

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                orderBy: [
                    {
                        field: "team_id"
                        order: ASC
                    }
                    {
                        field: "name"
                        order: ASC
                    }
                ]
            ) {
                team_id
                name
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    [
                        'team_id' => 2,
                        'name' => 'B',
                    ],
                    [
                        'team_id' => 2,
                        'name' => 'C',
                    ],
                    [
                        'team_id' => 5,
                        'name' => 'A',
                    ],
                ],
            ],
        ]);
    }

    public function testCanOrderWithRestrictedColumns(): void
    {
        factory(User::class)->create(['name' => 'B']);
        factory(User::class)->create(['name' => 'A']);

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                orderByRestricted: [
                    {
                        field: NAME
                        order: ASC
                    }
                ]
            ) {
                name
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    [
                        'name' => 'A',
                    ],
                    [
                        'name' => 'B',
                    ],
                ],
            ],
        ]);
    }

    /**
     * @deprecated will be removed in v5
     * @return void
     */
    public function testConfigureColumnArg(): void
    {
        config(['lighthouse.orderBy' => 'column']);

        factory(User::class)->create(['name' => 'B']);
        factory(User::class)->create(['name' => 'A']);

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                orderBy: [
                    {
                        column: "name"
                        order: ASC
                    }
                ]
            ) {
                name
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    [
                        'name' => 'A',
                    ],
                    [
                        'name' => 'B',
                    ],
                ],
            ],
        ]);
    }
}
