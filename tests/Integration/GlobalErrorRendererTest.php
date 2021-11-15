<?php

namespace Tests\Integration;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Orchestra\Testbench\Exceptions\Handler as OrchestraHandler;
use Tests\TestCase;
use Tests\Utils\Exceptions\WithExtensionsException;

class GlobalErrorRendererTest extends TestCase
{
    const MESSAGE = 'foo';
    const EXTENSIONS_CONTENT = ['bar' => 'baz'];

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $app->make('config');

        $config->set('lighthouse.route.middleware', [
            function () {
                throw new WithExtensionsException(self::MESSAGE, self::EXTENSIONS_CONTENT);
            },
        ]);
    }

    protected function resolveApplicationExceptionHandler($app): void
    {
        $app->singleton(ExceptionHandler::class, OrchestraHandler::class);
    }

    public function testCatchesErrorWithExtensions(): void
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
        ')->assertJson([
            'errors' => [
                [
                    'message' => self::MESSAGE,
                    'extensions' => self::EXTENSIONS_CONTENT,
                ],
            ],
        ]);
    }
}
