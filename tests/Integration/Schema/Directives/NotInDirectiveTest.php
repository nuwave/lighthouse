<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

final class NotInDirectiveTest extends DBTestCase
{
    public function testNotInIDs(): void
    {
        $user1 = factory(User::class)->create();
        assert($user1 instanceof User);

        $user2 = factory(User::class)->create();
        assert($user2 instanceof User);

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Query {
            users(notIDs: [ID!] @notIn(key: "id")): [User!]! @all
        }
        ';

        $user1ID = (string) $user1->id;
        $user2ID = (string) $user2->id;

        $this
            ->graphQL(/** @lang GraphQL */ '
            query ($notIDs: [ID!]) {
                users(notIDs: $notIDs) {
                    id
                }
            }
            ', [
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

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Query {
            users(notIDs: [ID!] @notIn(key: "id")): [User!]! @all
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            query ($notIDs: [ID!]) {
                users(notIDs: $notIDs) {
                    id
                }
            }
            ', [
                'notIDs' => null,
            ])
            ->assertJsonCount($users->count(), 'data.users');
    }

    public function testExplicitNullInArray(): void
    {
        factory(User::class, 2)->create();

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Query {
            users(notIDs: [ID] @notIn(key: "id")): [User!]! @all
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            query ($notIDs: [ID]) {
                users(notIDs: $notIDs) {
                    id
                }
            }
            ', [
                'notIDs' => [null],
            ])
            ->assertJsonCount(0, 'data.users');
    }

    public function testEmptyArray(): void
    {
        $users = factory(User::class, 2)->create();

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Query {
            users(notIDs: [ID!] @notIn(key: "id")): [User!]! @all
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            query ($notIDs: [ID!]) {
                users(notIDs: $notIDs) {
                    id
                }
            }
            ', [
                'notIDs' => [],
            ])
            ->assertJsonCount($users->count(), 'data.users');
    }
}
