<?php

namespace Tests\Integration\Schema\Directives;

use Nuwave\Lighthouse\Support\AppVersion;
use Tests\DBTestCase;
use Tests\Utils\Models\User;

class WhereJsonContainsDirectiveDBTest extends DBTestCase
{
    protected $schema = '
    type Query {
        users(foo: String! @whereJsonContains(key: "name->nested")): [User!]! @all
    }

    type User {
        name: String
    }
    ';

    protected function setUp(): void
    {
        parent::setUp();

        if (AppVersion::below(5.6)) {
            $this->markTestSkipped('Laravel supports whereJsonContains from version 5.6.');
        }
    }

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
