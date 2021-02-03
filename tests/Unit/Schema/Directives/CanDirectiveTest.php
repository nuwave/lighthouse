<?php

namespace Tests\Unit\Schema\Directives;

use Nuwave\Lighthouse\Exceptions\AuthorizationException;
use Nuwave\Lighthouse\Support\AppVersion;
use Tests\TestCase;
use Tests\Utils\Models\User;
use Tests\Utils\Policies\UserPolicy;

class CanDirectiveTest extends TestCase
{
    public function testThrowsIfNotAuthorized(): void
    {
        $this->be(new User);

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
        ')->assertGraphQLErrorCategory(AuthorizationException::CATEGORY);
    }

    public function testThrowsWithCustomMessageIfNotAuthorized(): void
    {
        // TODO remove with Laravel < 6 support
        if (AppVersion::below(6.0)) {
            $this->markTestSkipped('Version less than 6.0 do not support gate responses.');
        }

        $this->be(new User);

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

        $response = $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                name
            }
        }
        ');
        $response->assertGraphQLErrorCategory(AuthorizationException::CATEGORY);
        $response->assertJson([
            'errors' => [
                [
                    'message' => UserPolicy::SUPER_ADMINS_ONLY_MESSAGE,
                ],
            ],
        ]);
    }

    public function testThrowsFirstWithCustomMessageIfNotAuthorized(): void
    {
        // TODO remove with Laravel < 6 support
        if (AppVersion::below(6.0)) {
            $this->markTestSkipped('Version less than 6.0 do not support gate responses.');
        }

        $this->be(new User);

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

        $response = $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                name
            }
        }
        ');
        $response->assertGraphQLErrorCategory(AuthorizationException::CATEGORY);
        $response->assertJson([
            'errors' => [
                [
                    'message' => UserPolicy::SUPER_ADMINS_ONLY_MESSAGE,
                ],
            ],
        ]);
    }

    public function testPassesAuthIfAuthorized(): void
    {
        $user = new User;
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

    public function testAcceptsGuestUser(): void
    {
        if (AppVersion::below(5.7)) {
            $this->markTestSkipped('Version less than 5.7 do not support guest user.');
        }

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
        $user = new User;
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
        ')->assertGraphQLErrorCategory(AuthorizationException::CATEGORY);
    }

    public function testInjectArgsPassesClientArgumentToPolicy(): void
    {
        $this->be(new User);

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
        $this->be(new User);

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

    public function resolveUser(): User
    {
        $user = new User;
        $user->name = 'foo';

        return $user;
    }
}
