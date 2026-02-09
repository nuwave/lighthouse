<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

final class NotInDirectiveTest extends DBTestCase
{
    public function testNotInIDs(): void
    {
        $user1 = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user1);

        $user2 = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user2);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
        }

        type Query {
            users(notIDs: [ID!] @notIn(key: "id")): [User!]! @all
        }
        GRAPHQL;

        $user1ID = (string) $user1->id;
        $user2ID = (string) $user2->id;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            query ($notIDs: [ID!]) {
                users(notIDs: $notIDs) {
                    id
                }
            }
            GRAPHQL, [
                'notIDs' => [$user1ID],
            ])
            ->assertJson([
                'data' => [
                    'users' => [
                        [
                            'id' => $user2ID,
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
            users(notIDs: [ID!] @notIn(key: "id")): [User!]! @all
        }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            query ($notIDs: [ID!]) {
                users(notIDs: $notIDs) {
                    id
                }
            }
            GRAPHQL, [
                'notIDs' => null,
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
            users(notIDs: [ID] @notIn(key: "id")): [User!]! @all
        }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            query ($notIDs: [ID]) {
                users(notIDs: $notIDs) {
                    id
                }
            }
            GRAPHQL, [
                'notIDs' => [null],
            ])
            ->assertJsonCount(0, 'data.users');
    }

    public function testEmptyArray(): void
    {
        $users = factory(User::class, 2)->create();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
        }

        type Query {
            users(notIDs: [ID!] @notIn(key: "id")): [User!]! @all
        }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            query ($notIDs: [ID!]) {
                users(notIDs: $notIDs) {
                    id
                }
            }
            GRAPHQL, [
                'notIDs' => [],
            ])
            ->assertJsonCount($users->count(), 'data.users');
    }
}
