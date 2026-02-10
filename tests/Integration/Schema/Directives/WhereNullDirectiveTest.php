<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

final class WhereNullDirectiveTest extends DBTestCase
{
    public function testWhereNull(): void
    {
        $notNull = factory(User::class)->make();
        $this->assertInstanceOf(User::class, $notNull);
        $notNull->name = 'foo';
        $notNull->save();

        $null = factory(User::class)->make();
        $this->assertInstanceOf(User::class, $null);
        $null->name = null;
        $null->save();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
        }

        type Query {
            users(nameIsNull: Boolean @whereNull(key: "name")): [User!]! @all
        }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                users {
                    id
                }
            }
            GRAPHQL)
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
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                users(nameIsNull: null) {
                    id
                }
            }
            GRAPHQL)
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
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                users(nameIsNull: true) {
                    id
                }
            }
            GRAPHQL)
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
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                users(nameIsNull: false) {
                    id
                }
            }
            GRAPHQL)
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
