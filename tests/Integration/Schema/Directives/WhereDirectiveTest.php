<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

final class WhereDirectiveTest extends DBTestCase
{
    public function testAttachWhereFilterFromField(): void
    {
        $foo = factory(User::class)->make();
        assert($foo instanceof User);
        $foo->name = 'foo';
        $foo->save();

        $bar = factory(User::class)->make();
        assert($bar instanceof User);
        $bar->name = 'bar';
        $bar->save();

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Query {
            usersBeginningWithF: [User!]! @all @where(key: "name", operator: "like", value: "f%")
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                usersBeginningWithF {
                    id
                }
            }
            ')
            ->assertJsonCount(1, 'data.usersBeginningWithF');
    }
}
