<?php

namespace Tests\Unit\Auth;

use Nuwave\Lighthouse\Auth\CanDirective;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;
use Tests\TestCase;
use Tests\Utils\Models\User;
use Tests\Utils\Policies\UserPolicy;

final class CanDirectiveTest extends TestCase
{
    public function testThrowsIfNotAuthorized(): void
    {
        $this->be(new User());

        $this->schema = /** @lang GraphQL */ '
        type Query {
            user: User!
                @can(ability: "adminOnly")
                @mock
        }

        type User {
            name: String
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                name
            }
        }
        ')->assertGraphQLErrorMessage(AuthorizationException::MESSAGE);
    }

    public function testThrowsWithCustomMessageIfNotAuthorized(): void
    {
        $this->be(new User());

        $this->schema = /** @lang GraphQL */ '
        type Query {
            user: User!
                @can(ability: "superAdminOnly")
                @mock
        }

        type User {
            name: String
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                user {
                    name
                }
            }
            ')
            ->assertGraphQLErrorMessage(UserPolicy::SUPER_ADMINS_ONLY_MESSAGE);
    }

    public function testThrowsFirstWithCustomMessageIfNotAuthorized(): void
    {
        $this->be(new User());

        $this->schema = /** @lang GraphQL */ '
        type Query {
            user: User!
                @can(ability: ["superAdminOnly", "adminOnly"])
                @mock
        }

        type User {
            name: String
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                user {
                    name
                }
            }
            ')
            ->assertGraphQLErrorMessage(UserPolicy::SUPER_ADMINS_ONLY_MESSAGE);
    }

    public function testPassesAuthIfAuthorized(): void
    {
        $user = new User();
        $user->name = UserPolicy::ADMIN;
        $this->be($user);

        $this->mockResolver(function (): User {
            return $this->resolveUser();
        });

        $this->schema = /** @lang GraphQL */ '
        type Query {
            user: User!
                @can(ability: "adminOnly")
                @mock
        }

        type User {
            name: String
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                name
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'name' => 'foo',
                ],
            ],
        ]);
    }

    public function testChecksAgainstResolvedModels(): void
    {
        $user = new User();
        $user->name = UserPolicy::ADMIN;
        $this->be($user);

        $this->mockResolver(function (): User {
            return $this->resolveUser();
        });

        $this->schema = /** @lang GraphQL */ '
        type Query {
            user: User!
                @can(ability: "view", resolved: true)
                @mock
        }

        type User {
            name: String
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                name
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'name' => 'foo',
                ],
            ],
        ]);
    }

    public function testAcceptsGuestUser(): void
    {
        $this->mockResolver(function (): User {
            return $this->resolveUser();
        });

        $this->schema = /** @lang GraphQL */ '
        type Query {
            user: User!
                @can(ability: "guestOnly")
                @mock
        }

        type User {
            name: String
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                name
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'name' => 'foo',
                ],
            ],
        ]);
    }

    public function testPassesMultiplePolicies(): void
    {
        $user = new User();
        $user->name = UserPolicy::ADMIN;
        $this->be($user);

        $this->mockResolver(function (): User {
            return $this->resolveUser();
        });

        $this->schema = /** @lang GraphQL */ '
        type Query {
            user: User!
                @can(ability: ["adminOnly", "alwaysTrue"])
                @mock
        }

        type User {
            name: String
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                name
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'name' => 'foo',
                ],
            ],
        ]);
    }

    public function testProcessesTheArgsArgument(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            user: User!
                @can(ability: "dependingOnArg", args: [false])
                @mock
        }

        type User {
            name: String
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                name
            }
        }
        ')->assertGraphQLErrorMessage(AuthorizationException::MESSAGE);
    }

    public function testInjectArgsPassesClientArgumentToPolicy(): void
    {
        $this->be(new User());

        $this->mockResolver(function (): User {
            return $this->resolveUser();
        });

        $this->schema = /** @lang GraphQL */ '
        type Query {
            user(foo: String): User!
                @can(ability:"injectArgs", injectArgs: true)
                @mock
        }

        type User {
            name: String
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user(foo: "bar"){
                name
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'name' => 'foo',
                ],
            ],
        ]);
    }

    public function testInjectedArgsAndStaticArgs(): void
    {
        $this->be(new User());

        $this->mockResolver(function (): User {
            return $this->resolveUser();
        });

        $this->schema = /** @lang GraphQL */ '
        type Query {
            user(foo: String): User!
                @can(
                    ability: "argsWithInjectedArgs"
                    args: { foo: "static" }
                    injectArgs: true
                )
                @mock
        }

        type User {
            name: String
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user(foo: "dynamic"){
                name
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'name' => 'foo',
                ],
            ],
        ]);
    }

    /**
     * @dataProvider multipleMutuallyExclusiveArguments
     */
    public function testMultipleMutuallyExclusiveArgument(string $arguments): void
    {
        $this->expectExceptionObject(CanDirective::multipleMutuallyExclusiveArguments());

        $this->buildSchema(/** @lang GraphQL */ <<<GRAPHQL
        type Query {
            user(id: ID! @eq): User
                @can(ability: "view", {$arguments})
                @first
        }

        type User {
            id: ID!
            name: String!
        }

GRAPHQL
        );
    }

    /**
     * @return iterable<array{string}>
     */
    public function multipleMutuallyExclusiveArguments(): iterable
    {
        yield ['resolve: "id", query: true'];
        yield ['query: true, find: "id"'];
        yield ['find: "id", resolve: true'];
        yield ['resolve: "id", query: true, find: "id"'];
    }

    public function resolveUser(): User
    {
        $user = new User();
        $user->name = 'foo';

        return $user;
    }
}