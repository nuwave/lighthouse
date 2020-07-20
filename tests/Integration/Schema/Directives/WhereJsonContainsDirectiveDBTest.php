<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

class WhereJsonContainsDirectiveDBTest extends DBTestCase
{
    protected $schema = /** @lang GraphQL */ '
    type Query {
        users(foo: String! @whereJsonContains(key: "name->nested")): [User!]! @all
    }

    type User {
        name: String
    }
    ';

    public function testCanApplyWhereJsonContainsFilter(): void
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

        $this->graphQL(/** @lang GraphQL */ '
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
