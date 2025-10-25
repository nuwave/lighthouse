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
                    'laravel_function_property' => User::FUNCTION_PROPERTY_ATTRIBUTE_VALUE,
                ],
            ],
        ]);
    }

    /** @see https://github.com/nuwave/lighthouse/issues/2687 */
    public function testPhpProperty(): void
    {
        $user = factory(User::class)->create();
        assert($user instanceof User);

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        type User {
            id: ID!
            php_property: String
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
                    // TODO consider changing the default resolver so this returns User::PHP_PROPERTY_VALUE
                    'php_property' => null,
                ],
            ],
        ]);
    }

    /** @see https://github.com/nuwave/lighthouse/issues/2687 */
    public function testPrefersAttributeAccessorThatShadowsPhpProperty(): void
    {
        $user = factory(User::class)->create();
        assert($user instanceof User);

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        type User {
            id: ID!
            incrementing: String!
        }

        type Query {
            user(id: ID! @eq): User @find
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ '
        query ($id: ID!) {
            user(id: $id) {
                incrementing
            }
        }
        ', [
            'id' => $user->id,
        ])->assertJson([
            'data' => [
                'user' => [
                    'incrementing' => User::INCREMENTING_ATTRIBUTE_VALUE,
                ],
            ],
        ]);
    }

    /** @see https://github.com/nuwave/lighthouse/issues/2687 */
    public function testPrefersAttributeAccessorNullThatShadowsPhpProperty(): void
    {
        $user = factory(User::class)->create();
        assert($user instanceof User);

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        type User {
            id: ID!
            exists: Boolean
        }

        type Query {
            user(id: ID! @eq): User @find
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ '
        query ($id: ID!) {
            user(id: $id) {
                exists
            }
        }
        ', [
            'id' => $user->id,
        ])->assertJson([
            'data' => [
                'user' => [
                    'exists' => null,
                ],
            ],
        ]);
    }

    /** @see https://github.com/nuwave/lighthouse/issues/1671 */
    public function testExpensivePropertyIsOnlyCalledOnce(): void
    {
        $user = factory(User::class)->create();
        assert($user instanceof User);

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        type User {
            id: ID!
            expensive_property: Int!
        }

        type Query {
            user(id: ID! @eq): User @find
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ '
        query ($id: ID!) {
            user(id: $id) {
                expensive_property
            }
        }
        ', [
            'id' => $user->id,
        ])->assertJson([
            'data' => [
                'user' => [
                    // TODO consider changing the default resolver so this returns 1
                    'expensive_property' => 2,
                ],
            ],
        ]);
    }
}
