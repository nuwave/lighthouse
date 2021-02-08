<?php

namespace Tests\Integration;

use Tests\TestCase;
use Tests\Utils\NullErrorHandler;

class ErrorHandlersTest extends TestCase
{
    public function testErrorHandlerReturningNull(): void
    {
        config(['lighthouse.error_handlers' => [
            NullErrorHandler::class,
        ]]);

        $this->mockResolver(function () {
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
