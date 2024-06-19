<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

final class InDirectiveTest extends DBTestCase
{
    public function testInIDs(): void
    {
        $user1 = factory(User::class)->create();
        assert($user1 instanceof User);

        factory(User::class)->create();

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Query {
            users(ids: [ID!] @in(key: "id")): [User!]! @all
        }
        ';

        $user1ID = (string) $user1->id;

        $this
            ->graphQL(/** @lang GraphQL */ '
            query ($ids: [ID!]) {
                users(ids: $ids) {
                    id
                }
            }
            ', [
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

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Query {
            users(ids: [ID!] @in(key: "id")): [User!]! @all
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            query ($ids: [ID!]) {
                users(ids: $ids) {
                    id
                }
            }
            ', [
                'ids' => null,
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
            users(ids: [ID] @in(key: "id")): [User!]! @all
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            query ($ids: [ID]) {
                users(ids: $ids) {
                    id
                }
            }
            ', [
                'ids' => [null],
            ])
            ->assertJsonCount(0, 'data.users');
    }

    public function testEmptyArray(): void
    {
        factory(User::class, 2)->create();

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Query {
            users(ids: [ID!] @in(key: "id")): [User!]! @all
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            query ($ids: [ID!]) {
                users(ids: $ids) {
                    id
                }
            }
            ', [
                'ids' => [],
            ])
            ->assertJsonCount(0, 'data.users');
    }
}
