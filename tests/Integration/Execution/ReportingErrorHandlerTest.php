<?php declare(strict_types=1);

namespace Tests\Integration\Execution;

use GraphQL\Error\ClientAware;
use GraphQL\Error\Error;
use Illuminate\Contracts\Config\Repository;
use Nuwave\Lighthouse\Execution\ReportingErrorHandler;
use Tests\FakeExceptionHandler;
use Tests\TestCase;

final class ReportingErrorHandlerTest extends TestCase
{
    private FakeExceptionHandler $handler;

    /** @before */
    public function fakeExceptionHandling(): void
    {
        $this->afterApplicationCreated(function (): void {
            $this->withoutExceptionHandling();
            $this->handler = new FakeExceptionHandler();
        });
        $this->beforeApplicationDestroyed(function (): void {
            unset($this->handler);
        });
    }

    public function testHandlingWhenThereIsNoError(): void
    {
        $config = $this->app->make(Repository::class);
        $next = fn (?Error $error): array => match ($error) {
            null => ['error' => 'No error to report'],
            default => throw new \LogicException('Unexpected error: ' . $error::class),
        };

        $result = (new ReportingErrorHandler($this->handler, $config))(null, $next);

        $this->assertSame(['error' => 'No error to report'], $result);
        $this->handler->assertNothingReported();
    }

    public static function shouldAlwaysReport(): \Generator
    {
        yield 'Previous error is not client aware' => [new \Exception('Not client aware')];
        yield 'Previous error is not client safe' => [self::clientSafeError(false)];
    }

    /** @dataProvider shouldAlwaysReport */
    public function testErrorsThatShouldAlwaysReportWithDefaultConfig(\Exception $previousError): void
    {
        $config = $this->app->make(Repository::class);
        $error = new Error(previous: $previousError);
        $next = fn (Error $error): array => \compact('error');

        $result = (new ReportingErrorHandler($this->handler, $config))($error, $next);

        $this->assertSame(\compact('error'), $result);
        $this->handler->assertReported($previousError);
    }

    /** @dataProvider shouldAlwaysReport */
    public function testErrorsThatShouldAlwaysReportWithReportClientSafeEnabled(\Exception $previousError): void
    {
        $config = $this->app->make(Repository::class);
        $config->set('lighthouse.report_client_safe_errors', true);
        $error = new Error(previous: $previousError);
        $next = fn (Error $error): array => \compact('error');

        $result = (new ReportingErrorHandler($this->handler, $config))($error, $next);

        $this->assertSame(\compact('error'), $result);
        $this->handler->assertReported($previousError);
    }

    public static function clientSafeErrors(): \Generator
    {
        yield 'No previous error' => [null];
        yield 'Previous error is client safe' => [self::clientSafeError(true)];
    }

    /** @dataProvider clientSafeErrors */
    public function testClientSafeErrorsWithDefaultConfig(?\Exception $previousError): void
    {
        $config = $this->app->make(Repository::class);
        $error = new Error(previous: $previousError);
        $next = fn (Error $error): array => \compact('error');

        $result = (new ReportingErrorHandler($this->handler, $config))($error, $next);

        $this->assertSame(\compact('error'), $result);
        $this->handler->assertNothingReported();
    }

    /** @dataProvider clientSafeErrors */
    public function testClientSafeErrorsWithReportClientSafeEnabled(?\Exception $previousError): void
    {
        $config = $this->app->make(Repository::class);
        $config->set('lighthouse.report_client_safe_errors', true);
        $error = new Error(previous: $previousError);
        $next = fn (Error $error): array => \compact('error');

        $result = (new ReportingErrorHandler($this->handler, $config))($error, $next);

        $this->assertSame(\compact('error'), $result);
        $this->handler->assertReported(match ($previousError) {
            null => $error,
            default => $previousError,
        });
    }

    private static function clientSafeError(bool $clientSafe): \Exception
    {
        return new class($clientSafe) extends \Exception implements ClientAware {
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
