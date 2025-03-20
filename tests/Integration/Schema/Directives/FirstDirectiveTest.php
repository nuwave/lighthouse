<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

final class FirstDirectiveTest extends DBTestCase
{
    public function testReturnsASingleUser(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String!
        }

        type Query {
            user(id: ID @eq): User @first(model: "User")
        }
        ';

        $userA = factory(User::class)->create();
        assert($userA instanceof User);

        $userB = factory(User::class)->create();
        assert($userB instanceof User);

        $userC = factory(User::class)->create();
        assert($userC instanceof User);

        $this->graphQL(/** @lang GraphQL */ '
        query ($id: ID!) {
            user(id: $id) {
                id
            }
        }
        ', [
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
        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String!
        }

        type Query {
            user(name: String @eq): User @first(model: "User")
        }
        ';

        $userA1 = factory(User::class)->create();
        assert($userA1 instanceof User);
        $userA1->name = 'A';
        $userA1->save();

        $userA2 = factory(User::class)->create();
        assert($userA2 instanceof User);
        $userA2->name = 'A';
        $userA2->save();

        $userB = factory(User::class)->create();
        assert($userB instanceof User);
        $userB->name = 'B';
        $userB->save();

        $this->graphQL(/** @lang GraphQL */ '
        {
            user(name: "A") {
                id
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'id' => $userA1->id,
                ],
            ],
        ]);
    }
}
