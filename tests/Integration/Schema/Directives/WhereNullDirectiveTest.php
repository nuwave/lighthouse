<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

final class WhereNullDirectiveTest extends DBTestCase
{
    public function testWhereNull(): void
    {
        $notNull = factory(User::class)->make();
        assert($notNull instanceof User);
        $notNull->name = 'foo';
        $notNull->save();

        $null = factory(User::class)->make();
        assert($null instanceof User);
        $null->name = null;
        $null->save();

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Query {
            users(nameIsNull: Boolean @whereNull(key: "name")): [User!]! @all
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                users {
                    id
                }
            }
            ')
            ->assertJson([
                'data' => [
                    'users' => [
                        [
                            'id' => $notNull->id,
                        ],
                        [
                            'id' => $null->id,
                        ],
                    ],
                ],
            ]);

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                users(nameIsNull: null) {
                    id
                }
            }
            ')
            ->assertJson([
                'data' => [
                    'users' => [
                        [
                            'id' => $notNull->id,
                        ],
                        [
                            'id' => $null->id,
                        ],
                    ],
                ],
            ]);

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                users(nameIsNull: true) {
                    id
                }
            }
            ')
            ->assertJson([
                'data' => [
                    'users' => [
                        [
                            'id' => $null->id,
                        ],
                    ],
                ],
            ]);

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                users(nameIsNull: false) {
                    id
                }
            }
            ')
            ->assertJson([
                'data' => [
                    'users' => [
                        [
                            'id' => $notNull->id,
                        ],
                    ],
                ],
            ]);
    }
}
