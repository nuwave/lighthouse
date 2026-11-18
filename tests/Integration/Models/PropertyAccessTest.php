<?php declare(strict_types=1);

namespace Tests\Integration\Models;

use Tests\DBTestCase;
use Tests\Utils\Models\Role;
use Tests\Utils\Models\User;

final class PropertyAccessTest extends DBTestCase
{
    public function testLaravelDatabaseProperty(): void
    {
        $name = 'foobar';

        $user = factory(User::class)->make();
        $this->assertInstanceOf(User::class, $user);
        $user->name = $name;
        $user->save();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
            name: String!
        }

        type Query {
            user(id: ID! @eq): User @find
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($id: ID!) {
            user(id: $id) {
                name
            }
        }
        GRAPHQL, [
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
        $this->assertInstanceOf(User::class, $user);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
            laravel_function_property: String!
        }

        type Query {
            user(id: ID! @eq): User @find
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($id: ID!) {
            user(id: $id) {
                laravel_function_property
            }
        }
        GRAPHQL, [
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
        $this->assertInstanceOf(User::class, $user);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
            php_property: String
        }

        type Query {
            user(id: ID! @eq): User @find
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($id: ID!) {
            user(id: $id) {
                php_property
            }
        }
        GRAPHQL, [
            'id' => $user->id,
        ])->assertJson([
            'data' => [
                'user' => [
                    'php_property' => User::PHP_PROPERTY_VALUE,
                ],
            ],
        ]);
    }

    /** @see https://github.com/nuwave/lighthouse/issues/2687 */
    public function testPrefersAttributeAccessorThatShadowsPhpProperty(): void
    {
        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
            incrementing: String!
        }

        type Query {
            user(id: ID! @eq): User @find
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($id: ID!) {
            user(id: $id) {
                incrementing
            }
        }
        GRAPHQL, [
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
        $this->assertInstanceOf(User::class, $user);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
            exists: Boolean
        }

        type Query {
            user(id: ID! @eq): User @find
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($id: ID!) {
            user(id: $id) {
                exists
            }
        }
        GRAPHQL, [
            'id' => $user->id,
        ])->assertExactJson([
            'data' => [
                'user' => [
                    'exists' => null,
                ],
            ],
        ]);
    }

    public function testHidesEloquentFrameworkProperty(): void
    {
        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
            wasRecentlyCreated: Boolean
        }

        type Query {
            user(id: ID! @eq): User @find
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($id: ID!) {
            user(id: $id) {
                wasRecentlyCreated
            }
        }
        GRAPHQL, [
            'id' => $user->id,
        ])->assertExactJson([
            'data' => [
                'user' => [
                    'wasRecentlyCreated' => null,
                ],
            ],
        ]);
    }

    public function testHidesRedeclaredEloquentFrameworkProperty(): void
    {
        $role = factory(Role::class)->create();
        $this->assertInstanceOf(Role::class, $role);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Role {
            id: ID!
            timestamps: Boolean
        }

        type Query {
            role(id: ID! @eq): Role @find
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($id: ID!) {
            role(id: $id) {
                timestamps
            }
        }
        GRAPHQL, [
            'id' => $role->id,
        ])->assertExactJson([
            'data' => [
                'role' => [
                    'timestamps' => null,
                ],
            ],
        ]);
    }

    /** @see https://github.com/webonyx/graphql-php/issues/759 */
    public function testNullAccessorIsOnlyCalledOnce(): void
    {
        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
            null_accessor: String
        }

        type Query {
            user(id: ID! @eq): User @find
        }
        GRAPHQL;

        User::$nullAccessorCalls = 0;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($id: ID!) {
            user(id: $id) {
                null_accessor
            }
        }
        GRAPHQL, [
            'id' => $user->id,
        ])->assertExactJson([
            'data' => [
                'user' => [
                    'null_accessor' => null,
                ],
            ],
        ]);

        $this->assertSame(1, User::$nullAccessorCalls);
    }

    /** @see https://github.com/nuwave/lighthouse/issues/1671 */
    public function testExpensivePropertyIsOnlyCalledOnce(): void
    {
        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
            expensive_property: Int!
        }

        type Query {
            user(id: ID! @eq): User @find
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($id: ID!) {
            user(id: $id) {
                expensive_property
            }
        }
        GRAPHQL, [
            'id' => $user->id,
        ])->assertJson([
            'data' => [
                'user' => [
                    'expensive_property' => 1,
                ],
            ],
        ]);
    }
}
