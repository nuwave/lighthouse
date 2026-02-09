<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

final class FirstDirectiveTest extends DBTestCase
{
    public function testReturnsASingleUser(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
            name: String!
        }

        type Query {
            user(id: ID @eq): User @first(model: "User")
        }
        GRAPHQL;

        $userA = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $userA);

        $userB = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $userB);

        $userC = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $userC);

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($id: ID!) {
            user(id: $id) {
                id
            }
        }
        GRAPHQL, [
            'id' => $userB->id,
        ])->assertJson([
            'data' => [
                'user' => [
                    'id' => $userB->id,
                ],
            ],
        ]);
    }

    public function testReturnsASingleUserWhenMultiplesMatch(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
            name: String!
        }

        type Query {
            user(name: String @eq): User @first(model: "User")
        }
        GRAPHQL;

        $userA1 = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $userA1);
        $userA1->name = 'A';
        $userA1->save();

        $userA2 = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $userA2);
        $userA2->name = 'A';
        $userA2->save();

        $userB = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $userB);
        $userB->name = 'B';
        $userB->save();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            user(name: "A") {
                id
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'user' => [
                    'id' => $userA1->id,
                ],
            ],
        ]);
    }
}
