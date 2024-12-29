<?php declare(strict_types=1);

namespace Tests\Integration\Execution;

use Exception;
use Generator;
use GraphQL\Error\ClientAware;
use GraphQL\Error\Error;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Exceptions;
use LogicException;
use Nuwave\Lighthouse\Execution\ReportingErrorHandler;
use Tests\TestCase;

final class ReportingErrorHandlerTest extends TestCase
{
    /** @before */
    public function disableExceptionHandling(): void
    {
        $this->afterApplicationCreated(function (): void {
            $this->withoutExceptionHandling();
        });
    }

    public function testHandlingWhenThereIsNoError(): void
    {
        $handler = Exceptions::fake();
        $config = $this->app->make(Repository::class);
        $next = fn (?Error $error): array => match ($error) {
            null => ['No error to report'],
            default => throw new LogicException('Unexpected error: ' . $error::class),
        };

        $result = (new ReportingErrorHandler($handler, $config))(null, $next);

        $this->assertSame(['No error to report'], $result);
        $handler->assertReportedCount(0);
    }

    public static function shouldAlwaysReport(): Generator
    {
        yield 'Previous error is not client aware' => [new Exception('Not client aware')];
        yield 'Previous error is not client safe' => [self::clientSafeError(false)];
    }

    /** @dataProvider shouldAlwaysReport */
    public function testErrorsThatShouldAlwaysReportWithDefaultConfig(?Exception $previousError): void
    {
        $handler = Exceptions::fake();
        $config = $this->app->make(Repository::class);
        $error = new Error(previous: $previousError);
        $next = fn (Error $error): array => [$error];

        $result = (new ReportingErrorHandler($handler, $config))($error, $next);

        $this->assertSame([$error], $result);
        $handler->assertReported($previousError::class);
    }

    /** @dataProvider shouldAlwaysReport */
    public function testErrorsThatShouldAlwaysReportWithReportClientSafeEnabled(?Exception $previousError): void
    {
        $handler = Exceptions::fake();
        $config = $this->app->make(Repository::class);
        $config->set('lighthouse.report_client_safe_errors', true);
        $error = new Error(previous: $previousError);
        $next = fn (Error $error): array => [$error];

        $result = (new ReportingErrorHandler($handler, $config))($error, $next);

        $this->assertSame([$error], $result);
        $handler->assertReported($previousError::class);
    }

    public static function clientSafeErrors(): Generator
    {
        yield 'No previous error' => [null];
        yield 'Previous error is client safe' => [self::clientSafeError(true)];
    }

    /** @dataProvider clientSafeErrors */
    public function testClientSafeErrorsWithDefaultConfig(?Exception $previousError): void
    {
        $handler = Exceptions::fake();
        $config = $this->app->make(Repository::class);
        $error = new Error(previous: $previousError);
        $next = fn (Error $error): array => [$error];

        $result = (new ReportingErrorHandler($handler, $config))($error, $next);

        $this->assertSame([$error], $result);
        $handler->assertReportedCount(0);
    }

    /** @dataProvider clientSafeErrors */
    public function testClientSafeErrorsWithReportClientSafeEnabled(?Exception $previousError): void
    {
        $handler = Exceptions::fake();
        $config = $this->app->make(Repository::class);
        $config->set('lighthouse.report_client_safe_errors', true);
        $error = new Error(previous: $previousError);
        $next = fn (Error $error): array => [$error];

        $result = (new ReportingErrorHandler($handler, $config))($error, $next);

        $this->assertSame([$error], $result);
        $handler->assertReported(match ($previousError) {
            null => $error::class,
            default => $previousError::class,
        });
    }

    private static function clientSafeError(bool $clientSafe): Exception
    {
        return new class ($clientSafe) extends Exception implements ClientAware
        {
            public function __construct(
                private bool $clientSafe,
            ) {
                parent::__construct('Client Aware Error');
            }

            public function isClientSafe(): bool
            {
                return $this->clientSafe;
            }
        };
    }
}
