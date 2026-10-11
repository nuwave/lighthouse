<?php declare(strict_types=1);

namespace Tests\Unit\Auth;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Tests\TestCase;
use Tests\Utils\Models\User;

final class AuthDirectiveTest extends TestCase
{
    public function testResolveAuthenticatedUser(): void
    {
        $user = new User();
        $user->name = 'foo';
        $this->be($user);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            name: String!
        }

        type Query {
            user: User! @auth
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
                    'name' => $user->name,
                ],
            ],
        ]);
    }

    public function testResolveAuthenticatedUserWithGuardsArgument(): void
    {
        $user = new User();
        $user->name = 'foo';

        $authFactory = $this->app->make(AuthFactory::class);
        $authFactory->guard('web')->setUser($user);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            name: String!
        }

        type Query {
            user: User! @auth(guards: ["web"])
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
                    'name' => $user->name,
                ],
            ],
        ]);
    }

    public function testResolveAuthenticatedUserWithMultipleGuardsArgument(): void
    {
        $config = $this->app->make(ConfigRepository::class);

        $config->set('auth.guards', array_merge($config->get('auth.guards'), [
            'web' => [
                'driver' => 'session',
                'provider' => 'users',
            ],
            'api' => [
                'driver' => 'session',
                'provider' => 'users',
            ],
        ]));

        $user = new User();
        $user->name = 'foo';

        $authFactory = $this->app->make(AuthFactory::class);
        $authFactory->guard('api')->setUser($user);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            name: String!
        }

        type Query {
            user: User! @auth(guards: ["web", "api"])
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
                    'name' => $user->name,
                ],
            ],
        ]);
    }
}
