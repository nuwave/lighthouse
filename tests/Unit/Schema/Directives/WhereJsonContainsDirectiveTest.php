<?php

namespace Tests\Unit\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

class WhereJsonContainsDirectiveTest extends DBTestCase
{
    protected $schema = '
    type Query {
        users(foo: String! @whereJsonContains(key: "name->nested")): [User!]! @all
    }

    type User {
        name: String
    }    
    ';

    /**
     * @test
     */
    public function itCanOrderByTheGivenFieldAndSortOrderASC(): void
    {
        $nestedBar = \Safe\json_encode([
            'nested' => 'bar',
        ]);
        factory(User::class)->create([
            'name' => $nestedBar,
        ]);
        factory(User::class)->create([
            'name' => \Safe\json_encode([
                'nested' => 'baz',
            ]),
        ]);

        $this->graphQL('
        {
            users(foo: "bar") {
                name
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    [
                        'name' => $nestedBar,
                    ],
                ],
            ],
        ]);
    }
}
