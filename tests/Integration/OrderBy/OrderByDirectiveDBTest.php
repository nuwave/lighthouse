<?php

namespace Tests\Integration\OrderBy;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Tests\DBTestCase;
use Tests\Utils\Models\User;

class OrderByDirectiveDBTest extends DBTestCase
{
    protected $schema = /** @lang GraphQL */ '
    type Query {
        users(
            orderBy: _ @orderBy
            orderByRestricted: _ @orderBy(columns: ["name"])
            orderByRestrictedEnum: _ @orderBy(columnsEnum: "UserColumn")
        ): [User!]! @all
    }

    type User {
        name: String
        team_id: Int
    }

    enum UserColumn {
        NAME @enum(value: "name")
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

    public function testCanOrderByTheGivenFieldAndSortOrderDESC(): void
    {
        factory(User::class)->create(['name' => 'B']);
        factory(User::class)->create(['name' => 'A']);

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                orderBy: [
                    {
                        column: "name"
                        order: DESC
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
                        column: "team_id"
                        order: ASC
                    }
                    {
                        column: "name"
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
                        column: NAME
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

    public function testCanUseColumnEnumsArg(): void
    {
        factory(User::class)->create(['name' => 'B']);
        factory(User::class)->create(['name' => 'A']);

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(
                orderByRestrictedEnum: [
                    {
                        column: NAME
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

    public function testRejectsDefinitionWithDuplicateColumnArgument(): void
    {
        $this->expectException(DefinitionException::class);

        $this->buildSchema(/** @lang GraphQL */ '
        type Query {
            users(
                orderBy: _ @orderBy(columns: ["name"], columnsEnum: "UserColumn")
            ): [User!]! @all
        }

        type User {
            name: String
            team_id: Int
        }

        enum UserColumn {
            NAME @enum(value: "name")
        }
        ');
    }

    public function testCanOrderColumnOnField(): void
    {
        factory(User::class)->create(['name' => 'B']);
        factory(User::class)->create(['name' => 'A']);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            latestUsers: [User!]!
                @all
                @orderBy(column: "created_at" direction: DESC)
        }

        type User {
            name: String
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            latestUsers {
                name
            }
        }
        ')->assertExactJson([
            'data' => [
                'latestUsers' => [
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
}
