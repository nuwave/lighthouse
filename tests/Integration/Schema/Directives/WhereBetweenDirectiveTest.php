<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Illuminate\Support\Carbon;
use Tests\DBTestCase;
use Tests\Utils\Models\User;

final class WhereBetweenDirectiveTest extends DBTestCase
{
    public function testBetween(): void
    {
        $user1 = factory(User::class)->make();
        $this->assertInstanceOf(User::class, $user1);
        $user1->created_at = Carbon::createStrict(2022);
        $user1->save();

        $user2 = factory(User::class)->make();
        $this->assertInstanceOf(User::class, $user2);
        $user2->created_at = Carbon::createStrict(2023);
        $user2->save();

        $user3 = factory(User::class)->make();
        $this->assertInstanceOf(User::class, $user3);
        $user3->created_at = Carbon::createStrict(2024);
        $user3->save();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        scalar DateTime @scalar(class: "Nuwave\\Lighthouse\\Schema\\Types\\Scalars\\DateTime")

        type User {
            id: ID!
        }

        type Query {
            users(createdBetween: DateTimeRange @whereBetween(key: "created_at")): [User!]! @all
        }

        input DateTimeRange {
            from: DateTime!
            to: DateTime!
        }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                users(createdBetween: { from: "2022-06-06 00:00:00", to: "2023-06-06 00:00:00" }) {
                    id
                }
            }
            GRAPHQL)
            ->assertExactJson([
                'data' => [
                    'users' => [
                        [
                            'id' => (string) $user2->id,
                        ],
                    ],
                ],
            ]);
    }

    public function testExplicitNull(): void
    {
        $users = factory(User::class, 2)->create();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        scalar DateTime @scalar(class: "Nuwave\\Lighthouse\\Schema\\Types\\Scalars\\DateTime")

        type User {
            id: ID!
        }

        type Query {
            users(createdBetween: DateTimeRange @whereBetween(key: "created_at")): [User!]! @all
        }

        input DateTimeRange {
            from: DateTime!
            to: DateTime!
        }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                users(createdBetween: null) {
                    id
                }
            }
            GRAPHQL)
            ->assertGraphQLErrorFree()
            ->assertJsonCount($users->count(), 'data.users');
    }
}
