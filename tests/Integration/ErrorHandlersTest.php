<?php declare(strict_types=1);

namespace Tests\Integration;

use Tests\TestCase;
use Tests\Utils\NullErrorHandler;

final class ErrorHandlersTest extends TestCase
{
    public function testErrorHandlerReturningNull(): void
    {
        config(['lighthouse.error_handlers' => [
            NullErrorHandler::class,
        ]]);

        $this->mockResolver(static function (): void {
            throw new \Exception();
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
        ')->assertExactJson([
            'data' => [
                'foo' => null,
            ],
        ]);
    }
}
