<?php declare(strict_types=1);

namespace Tests\Unit\Support\Http\Middleware;

use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Http\Middleware\AttemptAuthentication;
use PHPUnit\Framework\Constraint\Callback;
use Tests\TestCase;
use Tests\Utils\Models\User;

final class AttemptAuthenticationTest extends TestCase
{
    public ?User $user = null;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $authManager = $app->make(AuthManager::class);
        $authManager->viaRequest('foo', fn (): ?User => $this->user);

        $config = $app->make(ConfigRepository::class);
        $config->set('lighthouse.route.middleware', [
            AttemptAuthentication::class,
        ]);
        $config->set('lighthouse.guards', ['foo']);
        $config->set('auth.guards.foo', [
            'driver' => 'foo',
            'provider' => 'users',
        ]);
    }

    public function testAttemptsAuthenticationGuest(): void
    {
        $this->mockResolver()
            ->with(
                null,
                [],
                new Callback(fn (GraphQLContext $context): bool => $this->user === null),
            );

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: ID @mock
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ');
    }

    public function testAttemptsAuthenticationUser(): void
    {
        $this->user = new User();

        $this->mockResolver()
            ->with(
                null,
                [],
                new Callback(fn (GraphQLContext $context): bool => $this->user === $context->user()),
            );

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: ID @mock
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ');
    }
}
