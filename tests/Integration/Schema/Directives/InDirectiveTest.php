<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

final class InDirectiveTest extends DBTestCase
{
    public function testInIDs(): void
    {
        $user1 = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user1);

        factory(User::class)->create();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
        }

        type Query {
            users(ids: [ID!] @in(key: "id")): [User!]! @all
        }
        GRAPHQL;

        $user1ID = (string) $user1->id;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            query ($ids: [ID!]) {
                users(ids: $ids) {
                    id
                }
            }
            GRAPHQL, [
                'ids' => [$user1ID],
            ])
            ->assertJson([
                'data' => [
                    'users' => [
                        [
                            'id' => $user1ID,
                        ],
                    ],
                ],
            ]);
    }

    public function testExplicitNull(): void
    {
        $users = factory(User::class, 2)->create();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
        }

        type Query {
            users(ids: [ID!] @in(key: "id")): [User!]! @all
        }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            query ($ids: [ID!]) {
                users(ids: $ids) {
                    id
                }
            }
            GRAPHQL, [
                'ids' => null,
            ])
            ->assertJsonCount($users->count(), 'data.users');
    }

    public function testExplicitNullInArray(): void
    {
        factory(User::class, 2)->create();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
        }

        type Query {
            users(ids: [ID] @in(key: "id")): [User!]! @all
        }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            query ($ids: [ID]) {
                users(ids: $ids) {
                    id
                }
            }
            GRAPHQL, [
                'ids' => [null],
            ])
            ->assertJsonCount(0, 'data.users');
    }

    public function testEmptyArray(): void
    {
        factory(User::class, 2)->create();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
        }

        type Query {
            users(ids: [ID!] @in(key: "id")): [User!]! @all
        }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            query ($ids: [ID!]) {
                users(ids: $ids) {
                    id
                }
            }
            GRAPHQL, [
                'ids' => [],
            ])
            ->assertJsonCount(0, 'data.users');
    }
}
