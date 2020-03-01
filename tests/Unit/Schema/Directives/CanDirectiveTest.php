<?php

namespace Tests\Unit\Schema\Directives;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;
use Tests\DBTestCase;
use Tests\Utils\Models\User;
use Tests\Utils\Policies\UserPolicy;

class CanDirectiveTest extends DBTestCase
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
        ')->assertErrorCategory(AuthorizationException::CATEGORY);
    }

    public function testPassesAuthIfAuthorized(): void
    {
        $user = new User;
        $user->name = UserPolicy::ADMIN;
        $this->be($user);

        $this->mockResolver([$this, 'resolveUser']);
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
        if ((float) $this->app->version() < 5.7) {
            $this->markTestSkipped('Version less than 5.7 do not support guest user.');
        }

        $this->mockResolver([$this, 'resolveUser']);
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

        $this->mockResolver([$this, 'resolveUser']);
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
        ')->assertErrorCategory(AuthorizationException::CATEGORY);
    }

    public function testInjectArgsPassesClientArgumentToPolicy(): void
    {
        $this->be(new User);

        $this->mockResolver([$this, 'resolveUser']);
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

        $this->mockResolver([$this, 'resolveUser']);
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

    public function testFindArgument(): void
    {
        $user = factory(User::class)->create(['name' => 'foo']);
        $this->be($user);

        $this->mockResolver([$this, 'resolveUser']);
        $this->schema = /** @lang GraphQL */ '
        type Query {
            user(foo: ID): User!
                @can(ability: "alwaysTrue", find: "foo")
                @mock
        }

        type User {
            name: String
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user(foo: 1) {
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

        $this->expectException(ModelNotFoundException::class);
        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                name
            }
        }
        ');
    }

    public function testFindArgumentNested(): void
    {
        $user = factory(User::class)->create(['name' => 'foo']);
        $this->be($user);

        $this->mockResolver([$this, 'resolveUser']);
        $this->schema = /** @lang GraphQL */ '
        type Query {
            user(input: FindUserInput): User!
                @can(ability: "alwaysTrue", find: "input.foo")
                @mock
        }

        type User {
            name: String
        }
        
        input FindUserInput {
            foo: ID
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user(input: {
                foo: 1
            }) {
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
