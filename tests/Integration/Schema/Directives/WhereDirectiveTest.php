<?php declare(strict_types=1);

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

    public function testCanIgnoreNulls(): void
    {
        $users = factory(User::class, 2)->create();

        $this->schema = /** @lang GraphQL */'
        scalar DateTime @scalar(class: "Nuwave\\\Lighthouse\\\Schema\\\Types\\\Scalars\\\DateTime")

        type User {
            id: ID!
            created_at: DateTime!
        }

        type Query {
            users(createdAfter: DateTime @where(key: "created_at", operator: ">", ignoreNull: true)): [User!]! @all
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */'
            query ($after: DateTime) {
                users(createdAfter: $after) {
                    id
                }
            }
            ', [
                'after' => null,
            ])
            ->assertGraphQLErrorFree()
            ->assertJsonCount($users->count(), 'data.users');
    }
}
