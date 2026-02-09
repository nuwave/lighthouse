<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

final class WhereJsonContainsDirectiveDBTest extends DBTestCase
{
    protected string $schema = /** @lang GraphQL */ '
    type Query {
        users(foo: String! @whereJsonContains(key: "name->nested")): [User!]! @all
    }

    type User {
        name: String
    }
    ';

    public function testApplyWhereJsonContainsFilter(): void
    {
        $nestedBar = \Safe\json_encode([
            'nested' => 'bar',
        ]);
        $userWithBar = factory(User::class)->make();
        $this->assertInstanceOf(User::class, $userWithBar);
        $userWithBar->name = $nestedBar;
        $userWithBar->save();

        $userWithBaz = factory(User::class)->make();
        $this->assertInstanceOf(User::class, $userWithBaz);
        $userWithBaz->name = \Safe\json_encode([
            'nested' => 'baz',
        ]);
        $userWithBaz->save();

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
