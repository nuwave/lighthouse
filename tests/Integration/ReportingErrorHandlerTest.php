<?php declare(strict_types=1);

namespace Tests\Integration;

use GraphQL\Error\Error;
use Illuminate\Contracts\Debug\ExceptionHandler;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

final class ReportingErrorHandlerTest extends TestCase
{
    protected string $schema = /** @lang GraphQL */ '
    type Query {
        foo: ID @mock
    }
    ';

    public function testReportsNonClientSafeErrors(): void
    {
        $exception = new \Exception('some internal error that was unexpected');
        $this->mockResolver(static fn () => throw $exception);

        $handler = $this->createMock(ExceptionHandler::class);
        $handler
            ->expects($this->atLeastOnce())
            ->method('report')
            ->with($exception);
        $this->app->singleton(ExceptionHandler::class, static fn (): MockObject => $handler);

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ');
    }

    public function testDoesNotReportClientSafeErrors(): void
    {
        $error = new Error('an expected error that is shown to clients');
        $this->mockResolver(static fn () => throw $error);

        $handler = $this->createMock(ExceptionHandler::class);
        $handler
            ->expects($this->never())
            ->method('report')
            ->with($error);
        $this->app->singleton(ExceptionHandler::class, static fn (): MockObject => $handler);

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ');
    }
}
