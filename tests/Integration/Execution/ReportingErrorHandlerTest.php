<?php declare(strict_types=1);

namespace Tests\Integration\Execution;

use GraphQL\Error\Error;
use Nuwave\Lighthouse\Execution\ReportingErrorHandler;
use Tests\FakeExceptionHandler;
use Tests\TestCase;
use Tests\Utils\Exceptions\ClientAwareException;

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
        $next = fn (?Error $error): array => match ($error) {
            null => ['error' => 'No error to report'],
            default => throw new \LogicException('Unexpected error: ' . $error::class),
        };

        $result = (new ReportingErrorHandler($this->handler))(null, $next);

        $this->assertSame(['error' => 'No error to report'], $result);
        $this->handler->assertNothingReported();
    }

    /** @return iterable<array{\Exception}> */
    public static function nonClientSafe(): iterable
    {
        yield 'Previous error is not client aware' => [new \Exception('Not client aware')];
        yield 'Previous error is not client safe' => [ClientAwareException::notClientSafe()];
    }

    /** @dataProvider nonClientSafe */
    public function testNonClientSafeErrors(\Exception $previousError): void
    {
        $error = new Error(previous: $previousError);
        $next = fn (Error $error): array => ['error' => $error];

        $result = (new ReportingErrorHandler($this->handler))($error, $next);

        $this->assertSame(['error' => $error], $result);
        $this->handler->assertReported($previousError);
    }

    /** @return iterable<array{\Exception|null}> */
    public static function clientSafeErrors(): iterable
    {
        yield 'No previous error' => [null];
        yield 'Previous error is client safe' => [ClientAwareException::clientSafe()];
    }

    /** @dataProvider clientSafeErrors */
    public function testClientSafeErrors(?\Exception $previousError): void
    {
        $error = new Error(previous: $previousError);
        $next = fn (Error $error): array => ['error' => $error];

        $result = (new ReportingErrorHandler($this->handler))($error, $next);

        $this->assertSame(['error' => $error], $result);
        $this->handler->assertNothingReported();
    }
}
