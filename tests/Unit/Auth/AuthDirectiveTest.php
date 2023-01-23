<?php

namespace Tests\Unit\Auth;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Tests\TestCase;
use Tests\Utils\Models\User;

final class AuthDirectiveTest extends TestCase
{
    public function testResolveAuthenticatedUser(): void
    {
        $user = new User();
        $user->name = 'foo';
        $this->be($user);

        $this->schema = /** @lang GraphQL */ '
        type User {
            name: String!
        }

        type Query {
            user: User! @auth
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
                    'name' => $user->name,
                ],
            ],
        ]);
    }

    public function testResolveAuthenticatedUserWithGuardArgument(): void
    {
        $user = new User();
        $user->name = 'foo';

        $authFactory = $this->app->make(AuthFactory::class);
        $authFactory->guard('web')->setUser($user);

        $this->schema = /** @lang GraphQL */ '
        type User {
            name: String!
        }

        type Query {
            user: User! @auth(guard: "web")
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
                    'name' => $user->name,
                ],
            ],
        ]);
    }
}
