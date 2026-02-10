<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

final class WhereNotNullDirectiveTest extends DBTestCase
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
            users(nameIsNotNull: Boolean @whereNotNull(key: "name")): [User!]! @all
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
                users(nameIsNotNull: null) {
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
                users(nameIsNotNull: true) {
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

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                users(nameIsNotNull: false) {
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
    }
}
