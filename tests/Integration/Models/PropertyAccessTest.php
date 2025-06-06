<?php declare(strict_types=1);

namespace Models;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

class PropertyAccessTest extends DBTestCase
{
    public function testLaravelDatabaseProperty(): void
    {
        factory(User::class)->create(['name' => 'foobar']);

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
        {
            user(id: 1) {
                name
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'name' => 'foobar',
                ],
            ],
        ]);
    }

    public function testLaravelFunctionProperty(): void
    {
        factory(User::class)->create();

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
        {
            user(id: 1) {
                laravel_function_property
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'laravel_function_property' => 'foo',
                ],
            ],
        ]);
    }

    public function testPhpProperty(): void
    {
        factory(User::class)->create();

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
        {
            user(id: 1) {
                php_property
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'php_property' => 'foo',
                ],
            ],
        ]);
    }
}
