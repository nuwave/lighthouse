<?php

namespace Tests\Integration;

use Exception;
use GraphQL\Error\Error;
use Illuminate\Auth\AuthManager;
use Nuwave\Lighthouse\Support\Http\Middleware\AttemptAuthentication;
use Tests\TestCase;
use Tests\Utils\NullErrorHandler;

class GlobalErrorRendererTest extends TestCase
{

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $app->make('config');

        $config->set('lighthouse.route.middleware', [
            function () {
                throw new Error('safe');
            }
        ]);
    }

    public function testErrorHandlerReturningNull(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: ID
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertExactJson([
            'data' => [
                'foo' => null,
            ],
        ]);
    }
}
