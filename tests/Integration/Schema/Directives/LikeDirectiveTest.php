<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

class LikeDirectiveTest extends DBTestCase
{
    public function testLikeEnd(): void
    {
        $userA = factory(User::class)->create(['name' => 'Alan']);
        $userB = factory(User::class)->create(['name' => 'Alex']);
        $userC = factory(User::class)->create(['name' => 'Aaron']);

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users(name: String! @like(percentage: END): [User!]
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user(name: "Al") {
                name
            }
        }
        ')->assertJsonFragment([
            'users' => [
                [
                    'name' => 'Alan',
                ],
                [
                    'name' => 'Alex',
                ],
            ],
        ]);
    }

    public function testLikeBoth(): void
    {
        $userA = factory(User::class)->create(['name' => 'Alan']);
        $userB = factory(User::class)->create(['name' => 'Alex']);
        $userC = factory(User::class)->create(['name' => 'Aaron']);

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users(name: String! @like(percentage: BOTH): [User!]
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user(name: "l") {
                name
            }
        }
        ')->assertJsonFragment([
            'users' => [
                [
                    'name' => 'Alan',
                ],
                [
                    'name' => 'Alex',
                ],
            ],
        ]);
    }

    public function testLikeEscape(): void
    {
        $userA = factory(User::class)->create(['name' => 'Alan']);
        $userB = factory(User::class)->create(['name' => 'Alex']);
        $userC = factory(User::class)->create(['name' => 'Aar%on']);

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users(name: String! @like(percentage: BOTH): [User!]
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user(name: "ar%") {
                name
            }
        }
        ')->assertJsonFragment([
            'users' => [
                [
                    'name' => 'Aar%on',
                ],
            ],
        ]);
    }

}
