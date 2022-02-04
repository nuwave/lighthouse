<?php

namespace Tests\Integration;

use GraphQL\Error\Error;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Tests\TestCase;

class ReportingErrorHandlerTest extends TestCase
{
    protected $schema = /** @lang GraphQL */ '
    type Query {
        foo: ID @mock
    }
    ';

    public function testReportsNonClientSafeErrors(): void
    {
        $exception = new \Exception('some internal error that was unexpected');
        $this->mockResolver(function () use ($exception) {
            throw $exception;
        });

        $handler = $this->createMock(ExceptionHandler::class);
        $handler
            ->expects($this->atLeastOnce())
            ->method('report')
            ->with($exception);
        $this->app->singleton(ExceptionHandler::class, function () use ($handler) {
            return $handler;
        });

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ');
    }

    public function testDoesNotReportClientSafeErrors(): void
    {
        $error = new Error('an expected error that is shown to clients');
        $this->mockResolver(function () use ($error) {
            throw $error;
        });

        $handler = $this->createMock(ExceptionHandler::class);
        $handler
            ->expects($this->never())
            ->method('report')
            ->with($error);
        $this->app->singleton(ExceptionHandler::class, function () use ($handler) {
            return $handler;
        });

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ');
    }
}
