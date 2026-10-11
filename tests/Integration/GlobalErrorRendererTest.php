<?php declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Orchestra\Testbench\Exceptions\Handler as OrchestraHandler;
use Tests\TestCase;
use Tests\Utils\Exceptions\WithExtensionsException;

final class GlobalErrorRendererTest extends TestCase
{
    public const MESSAGE = 'foo';

    public const EXTENSIONS_CONTENT = ['bar' => 'baz'];

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $config = $app->make(ConfigRepository::class);
        $config->set('lighthouse.route.middleware', [
            static fn () => throw new WithExtensionsException(self::MESSAGE, self::EXTENSIONS_CONTENT),
        ]);
    }

    protected function resolveApplicationExceptionHandler($app): void
    {
        $app->singleton(ExceptionHandler::class, OrchestraHandler::class);
    }

    public function testCatchesErrorWithExtensions(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo: ID
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo
        }
        GRAPHQL)->assertJson([
            'errors' => [
                [
                    'message' => self::MESSAGE,
                    'extensions' => self::EXTENSIONS_CONTENT,
                ],
            ],
        ]);
    }
}
