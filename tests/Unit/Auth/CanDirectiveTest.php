<?php declare(strict_types=1);

namespace Tests\Unit\Auth;

use Nuwave\Lighthouse\Auth\CanDirective;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;
use Tests\TestCase;
use Tests\Utils\Models\User;
use Tests\Utils\Policies\UserPolicy;

/**
 * TODO remove with v7.
 */
final class CanDirectiveTest extends TestCase
{
    public function testThrowsIfNotAuthorized(): void
    {
        $this->be(new User());

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            user: User!
                @can(ability: "adminOnly")
                @mock
        }

        type User {
            name: String
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            user {
                name
            }
        }
        GRAPHQL)->assertGraphQLErrorMessage(AuthorizationException::MESSAGE);
    }

    public function testThrowsWithCustomMessageIfNotAuthorized(): void
    {
        $this->be(new User());

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            user: User!
                @can(ability: "superAdminOnly")
                @mock
        }

        type User {
            name: String
        }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                user {
                    name
                }
            }
            GRAPHQL)
            ->assertGraphQLErrorMessage(UserPolicy::SUPER_ADMINS_ONLY_MESSAGE);
    }

    public function testThrowsFirstWithCustomMessageIfNotAuthorized(): void
    {
        $this->be(new User());

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            user: User!
                @can(ability: ["superAdminOnly", "adminOnly"])
                @mock
        }

        type User {
            name: String
        }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                user {
                    name
                }
            }
            GRAPHQL)
            ->assertGraphQLErrorMessage(UserPolicy::SUPER_ADMINS_ONLY_MESSAGE);
    }

    public function testPassesAuthIfAuthorized(): void
    {
        $user = new User();
        $user->name = UserPolicy::ADMIN;
        $this->be($user);

        $this->mockResolver(fn (): User => $this->resolveUser());

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            user: User!
                @can(ability: "adminOnly")
                @mock
        }

        type User {
            name: String
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            user {
                name
            }
        }
        GRAPHQL)->assertJson([
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

        $this->mockResolver(fn (): User => $this->resolveUser());

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            user: User!
                @can(ability: "view", resolved: true)
                @mock
        }

        type User {
            name: String
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            user {
                name
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'user' => [
                    'name' => 'foo',
                ],
            ],
        ]);
    }

    public function testAcceptsGuestUser(): void
    {
        $this->mockResolver(fn (): User => $this->resolveUser());

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            user: User!
                @can(ability: "guestOnly")
                @mock
        }

        type User {
            name: String
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            user {
                name
            }
        }
        GRAPHQL)->assertJson([
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

        $this->mockResolver(fn (): User => $this->resolveUser());

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            user: User!
                @can(ability: ["adminOnly", "alwaysTrue"])
                @mock
        }

        type User {
            name: String
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            user {
                name
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'user' => [
                    'name' => 'foo',
                ],
            ],
        ]);
    }

    public function testProcessesTheArgsArgument(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            user: User!
                @can(ability: "dependingOnArg", args: [false])
                @mock
        }

        type User {
            name: String
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            user {
                name
            }
        }
        GRAPHQL)->assertGraphQLErrorMessage(AuthorizationException::MESSAGE);
    }

    public function testInjectArgsPassesClientArgumentToPolicy(): void
    {
        $this->be(new User());

        $this->mockResolver(fn (): User => $this->resolveUser());

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            user(foo: String): User!
                @can(ability: "injectArgs", injectArgs: true)
                @mock
        }

        type User {
            name: String
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            user(foo: "bar") {
                name
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'user' => [
                    'name' => 'foo',
                ],
            ],
        ]);
    }

    public function testChecksAgainstRootModel(): void
    {
        $this->be(new User());

        $this->mockResolver(fn (): User => $this->resolveUser());

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            user(foo: String): User! @mock
        }

        type User {
            name: String @can(ability: "view", root: true)
            email: String @can(ability: "superAdminOnly", root: true)
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            user(foo: "bar") {
                name
                email
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'user' => [
                    'name' => 'foo',
                    'email' => null,
                ],
            ],
        ])->assertJsonFragment([
            'message' => 'Only super admins allowed',
        ]);
    }

    public function testInjectedArgsAndStaticArgs(): void
    {
        $this->be(new User());

        $this->mockResolver(fn (): User => $this->resolveUser());

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            user(foo: "dynamic") {
                name
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'user' => [
                    'name' => 'foo',
                ],
            ],
        ]);
    }

    public static function resolveUser(): User
    {
        $user = new User();
        $user->name = 'foo';
        $user->email = 'test@example.com';

        return $user;
    }

    public function testThrowsIfResolvedIsUsedOnMutation(): void
    {
        $this->expectExceptionObject(CanDirective::resolvedIsUnsafeInMutations('foo'));
        $this->buildSchema(/** @lang GraphQL */ <<<'GRAPHQL'
        type Mutation {
            foo: ID @can(resolved: true)
        }
        GRAPHQL);
    }
}
