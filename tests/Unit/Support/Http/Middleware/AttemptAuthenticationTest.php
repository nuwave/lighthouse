<?php

namespace Tests\Unit\Support\Http\Middleware;

use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Nuwave\Lighthouse\Schema\Context;
use Nuwave\Lighthouse\Support\Http\Middleware\AttemptAuthentication;
use PHPUnit\Framework\Constraint\Callback;
use Tests\TestCase;
use Tests\Utils\Models\User;

final class AttemptAuthenticationTest extends TestCase
{
    /** @var \Tests\Utils\Models\User|null */
    public $user;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $authManager = $app->make(AuthManager::class);
        $authManager->viaRequest('foo', function () {
            return $this->user;
        });

        $config = $app->make(ConfigRepository::class);
        $config->set('lighthouse.route.middleware', [
            AttemptAuthentication::class,
        ]);
        $config->set('lighthouse.guard', 'foo');
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
                new Callback(function (Context $context) {
                    return null === $this->user;
                })
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
                new Callback(function (Context $context) {
                    return $this->user === $context->user();
                })
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
