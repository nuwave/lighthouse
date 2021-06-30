<?php

namespace Tests\Unit\Testing;

use GraphQL\Error\Error;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Tests\TestCase;

class MakesGraphQLRequestsTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $app->make(ConfigRepository::class);
        $config->set('lighthouse.route.prefix', 'prefix');
    }

    public function testGraphQLEndpointUrlWithPrefix(): void
    {
        $this->assertSame(
            'http://localhost/prefix/graphql',
            $this->graphQLEndpointUrl()
        );
    }

    public function testRethrowGraphQLErrors(): void
    {
        $error = new Error('Would not be rethrown by graphql-php with any combination of flags');
        $this->mockResolver(static function () use ($error): void {
            throw $error;
        });

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: ID @mock
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertGraphQLErrorMessage($error->getMessage());

        $this->rethrowGraphQLErrors();

        $this->expectExceptionObject($error);
        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ');
    }
}
