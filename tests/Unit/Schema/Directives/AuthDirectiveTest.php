<?php

namespace Tests\Unit\Schema\Directives;

use Tests\TestCase;
use Tests\Utils\Models\User;

class AuthDirectiveTest extends TestCase
{
    public function testCanResolveAuthenticatedUser(): void
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

    public function testCanResolveAuthenticatedUserWithGuardArgument(): void
    {
        $user = new User();
        $user->name = 'foo';

        $this->app['auth']->guard('api')->setUser($user);

        $this->schema = /** @lang GraphQL */ '
        type User {
            name: String!
        }

        type Query {
            user: User! @auth(guard: "api")
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
