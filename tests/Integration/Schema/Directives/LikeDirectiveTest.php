<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

final class LikeDirectiveTest extends DBTestCase
{
    public function testLikeClientsCanPassWildcards(): void
    {
        $this->createUserWithName('Alan');
        $this->createUserWithName('Alex');
        $this->createUserWithName('Aaron');

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            name: String!
        }

        type Query {
            users(
                name: String! @like
            ): [User!]! @all
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            users(name: "Al%") {
                name
            }
        }
        GRAPHQL)->assertJsonFragment([
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
        $this->createUserWithName('Alan');
        $this->createUserWithName('Alex');
        $this->createUserWithName('Aaron');

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            name: String!
        }

        type Query {
            users(
                name: String! @like(template: "%{}%")
            ): [User!]! @all
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            users(name: "l") {
                name
            }
        }
        GRAPHQL)->assertJsonFragment([
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
        $this->createUserWithName('Aaron');
        $this->createUserWithName('Aar%on');
        $this->createUserWithName('Aar%');
        $this->createUserWithName('Aar%toomuch');

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users(
                name: String! @like(template: "%{}__")
            ): [User!] @all
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            users(name: "ar%") {
                name
            }
        }
        GRAPHQL)->assertJsonFragment([
            'users' => [
                [
                    'name' => 'Aar%on',
                ],
            ],
        ]);
    }

    public function testLikeOnField(): void
    {
        $this->createUserWithName('Alex');
        $this->createUserWithName('Aaron');

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
            name: String!
        }

        type Query {
            users: [User!]
                @all
                @like(key: "name", value: "%ex")
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            users {
                name
            }
        }
        GRAPHQL)->assertJsonFragment([
            'users' => [
                [
                    'name' => 'Alex',
                ],
            ],
        ]);
    }

    private function createUserWithName(string $name): User
    {
        $user = factory(User::class)->make();
        $this->assertInstanceOf(User::class, $user);
        $user->name = $name;
        $user->save();

        return $user;
    }
}
