<?php declare(strict_types=1);

namespace Tests\Integration\Models;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

final class PropertyAccessTest extends DBTestCase
{
    public function testLaravelDatabaseProperty(): void
    {
        $name = 'foobar';

        $user = factory(User::class)->make();
        assert($user instanceof User);
        $user->name = $name;
        $user->save();

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        type User {
            id: ID!
            name: String!
        }

        type Query {
            user(id: ID! @eq): User @find
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ '
        query ($id: ID!) {
            user(id: $id) {
                name
            }
        }
        ', [
            'id' => $user->id,
        ])->assertJson([
            'data' => [
                'user' => [
                    'name' => $name,
                ],
            ],
        ]);
    }

    public function testLaravelFunctionProperty(): void
    {
        $user = factory(User::class)->create();
        assert($user instanceof User);

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        type User {
            id: ID!
            laravel_function_property: String!
        }

        type Query {
            user(id: ID! @eq): User @find
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ '
        query ($id: ID!) {
            user(id: $id) {
                laravel_function_property
            }
        }
        ', [
            'id' => $user->id,
        ])->assertJson([
            'data' => [
                'user' => [
                    'laravel_function_property' => 'foo',
                ],
            ],
        ]);
    }

    public function testPhpProperty(): void
    {
        $user = factory(User::class)->create();
        assert($user instanceof User);

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        type User {
            id: ID!
            php_property: String!
        }

        type Query {
            user(id: ID! @eq): User @find
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ '
        query ($id: ID!) {
            user(id: $id) {
                php_property
            }
        }
        ', [
            'id' => $user->id,
        ])->assertJson([
            'data' => [
                'user' => [
                    'php_property' => 'foo',
                ],
            ],
        ]);
    }
}
