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

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo: ID @mock
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo
        }
        GRAPHQL)->assertExactJson([
            'data' => [
                'foo' => null,
            ],
        ]);
    }
}
