<?php declare(strict_types=1);

namespace Tests\Unit\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Tests\TestCase;
use Tests\Utils\Models\User;

final class UserContextTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schema = /** @lang GraphQL */ '
        type User {
            name: String!
        }

        type Query {
            user: User @mock
        }
        ';

        $this->mockResolver(static fn ($_, array $args, GraphQLContext $context): ?Authenticatable => $context->user());
    }

    public function testResolveAuthenticatedUserUsingContext(): void
    {
        $user = new User();
        $user->name = 'foo';
        $this->be($user);

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

    public function testResolveGuestUserUsingContext(): void
    {
        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                name
            }
        }
        ')->assertJson([
            'data' => [
                'user' => null,
            ],
        ]);
    }

    public function testResolveAuthenticatedUserUsingContextWithMultipleGuards(): void
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

        $config->set('lighthouse.guards', ['web', 'api']);

        $user = new User();
        $user->name = 'foo';

        $authFactory = $this->app->make(AuthFactory::class);
        $authFactory->guard('api')->setUser($user);

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
