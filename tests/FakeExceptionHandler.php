<?php declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Debug\ExceptionHandler;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Response;

final class FakeExceptionHandler implements ExceptionHandler
{
    /** @var array<\Throwable> */
    private array $reported = [];

    public function report(\Throwable $e): void
    {
        $this->reported[] = $e;
    }

    public function shouldReport(\Throwable $e): bool
    {
        return true;
    }

    public function assertNothingReported(): void
    {
        Assert::assertEmpty($this->reported);
    }

    public function assertReported(\Throwable $e): void
    {
        Assert::assertContainsEquals($e, $this->reported);
    }

    public function render($request, \Throwable $e): Response
    {
        throw $e;
    }

    public function renderForConsole($output, \Throwable $e): void {}
}
