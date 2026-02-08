<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

final class WhereDirectiveTest extends DBTestCase
{
    public function testAttachWhereFilterFromField(): void
    {
        $foo = factory(User::class)->make();
        $this->assertInstanceOf(User::class, $foo);
        $foo->name = 'foo';
        $foo->save();

        $bar = factory(User::class)->make();
        $this->assertInstanceOf(User::class, $bar);
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

    public function testIgnoreNull(): void
    {
        $userWithoutEmail = factory(User::class)->make();
        $this->assertInstanceOf(User::class, $userWithoutEmail);
        $userWithoutEmail->email = null;
        $userWithoutEmail->save();

        $userWithEmail = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $userWithEmail);

        $this->schema = /** @lang GraphQL */ '
        scalar DateTime @scalar(class: "Nuwave\\\Lighthouse\\\Schema\\\Types\\\Scalars\\\DateTime")

        type User {
            id: ID!
            email: String
        }

        type Query {
            usersIgnoreNull(email: String @where(ignoreNull: true)): [User!]! @all
            usersExplicitNull(email: String @where): [User!]! @all
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                usersIgnoreNull(email: null) {
                    id
                }

                usersExplicitNull(email: null) {
                    id
                }
            }
            ')
            ->assertGraphQLErrorFree()
            ->assertJsonCount(2, 'data.usersIgnoreNull')
            ->assertJsonPath('data.usersIgnoreNull', [
                ['id' => (string) $userWithoutEmail->id],
                ['id' => (string) $userWithEmail->id],
            ])
            ->assertJsonCount(1, 'data.usersExplicitNull')
            ->assertJsonPath('data.usersExplicitNull', [[
                'id' => (string) $userWithoutEmail->id,
            ]]);
    }
}
