<?php

namespace Tests\Unit\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\User;
use Nuwave\Lighthouse\Exceptions\DefinitionException;

class OrderByDirectiveTest extends DBTestCase
{
    protected $schema = '
    type Query {
        users(orderBy: [OrderByClause!] @orderBy): [User!]! @all
    }

    type User {
        name: String
        team_id: Int
    }    
    ';

    public function testCanOrderByTheGivenFieldAndSortOrderASC(): void
    {
        factory(User::class)->create(['name' => 'B']);
        factory(User::class)->create(['name' => 'A']);

        $this->graphQL('
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

        $this->graphQL('
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

    public function testCanOrderByMultipleFields(): void
    {
        factory(User::class)->create(['name' => 'B', 'team_id' => 2]);
        factory(User::class)->create(['name' => 'A', 'team_id' => 5]);
        factory(User::class)->create(['name' => 'C', 'team_id' => 2]);

        $this->graphQL('
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

    public function testThrowsOnInvalidDefinition(): void
    {
        $this->expectException(DefinitionException::class);

        $this->buildSchema('
        type Query {
            foo(bar: Int @orderBy): Int
        }
        ');
    }
}
