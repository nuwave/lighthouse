<?php

namespace Tests\Unit\Support\Http\Middleware;

use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Schema\Context;
use PHPUnit\Framework\Constraint\Callback;
use Tests\TestCase;
use Nuwave\Lighthouse\Support\Http\Middleware\AttemptAuthentication;
use Tests\Utils\Models\User;

class AttemptAuthenticationTest extends TestCase
{
    /** @var \Tests\Utils\Models\User|null */
    public $user;

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        /** @var \Illuminate\Auth\AuthManager $authManager */
        $authManager = $app->make(AuthManager::class);
        $authManager->viaRequest('foo', function () {
            dd($this->user);
            return $this->user;
        });

        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $app['config'];

        $config->set('lighthouse.middleware', [
            AttemptAuthentication::class,
        ]);
//        dd($config->get('auth'));
        $config->set('auth.guards.api.driver', 'foo');

    }

    public function testAttemptsAuthenticationGuest(): void
    {
        /** @var \Nuwave\Lighthouse\Support\Http\Middleware\AttemptAuthentication $middleware */
        $middleware = app(AttemptAuthentication::class);
        $middleware->handle(new Request(), function() {});


        $this->mockResolver()
            ->with(
                null,
                [],
                null
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

        $a = $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ');

    }
}
