<?php

namespace Tests\Integration;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Tests\TestCase;

class ReportHandlerTest extends TestCase
{
    public function testReportsErrors(): void
    {
        $exception = new \Exception('foobar');
        $this->mockResolver(function () use ($exception) {
            throw $exception;
        });

        $handler = $this->createMock(ExceptionHandler::class);
        $handler
            ->expects($this->atLeastOnce())
            ->method('report')
            ->with($exception);
        app()->singleton(ExceptionHandler::class, function () use ($handler) {
            return $handler;
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
        ');
    }
}
