<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

class LikeDirectiveTest extends DBTestCase
{
    public function testLikeClientsCanPassWildcards(): void
    {
        factory(User::class)->create(['name' => 'Alan']);
        factory(User::class)->create(['name' => 'Alex']);
        factory(User::class)->create(['name' => 'Aaron']);

        $this->schema = /** @lang GraphQL */ '
        type User {
            name: String!
        }

        type Query {
            users(
                name: String! @like
            ): [User!]! @all
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(name: "Al%") {
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

    public function testLikeWithWildcardsInTemplate(): void
    {
        factory(User::class)->create(['name' => 'Alan']);
        factory(User::class)->create(['name' => 'Alex']);
        factory(User::class)->create(['name' => 'Aaron']);

        $this->schema = /** @lang GraphQL */ '
        type User {
            name: String!
        }

        type Query {
            users(
                name: String! @like(template: "%{}%")
            ): [User!]! @all
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(name: "l") {
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

    public function testLikeClientWildcardsAreEscapedFromTemplate(): void
    {
        factory(User::class)->create(['name' => 'Aaron']);
        factory(User::class)->create(['name' => 'Aar%on']);
        factory(User::class)->create(['name' => 'Aar%']);
        factory(User::class)->create(['name' => 'Aar%toomuch']);

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users(
                name: String! @like(template: "%{}__")
            ): [User!] @all
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(name: "ar%") {
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

    public function testLikeOnField(): void
    {
        factory(User::class)->create(['name' => 'Alex']);
        factory(User::class)->create(['name' => 'Aaron']);

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users: [User!]
                @all
                @like(key: "name", value: "%ex")
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            users {
                name
            }
        }
        ')->assertJsonFragment([
            'users' => [
                [
                    'name' => 'Alex',
                ],
            ],
        ]);
    }
}
