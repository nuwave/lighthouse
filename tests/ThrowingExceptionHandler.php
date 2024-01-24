<?php declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Symfony\Component\HttpFoundation\Response;

final class ThrowingExceptionHandler implements ExceptionHandler
{
    public function report(\Throwable $e): void {}

    public function shouldReport(\Throwable $e): bool
    {
        return false;
    }

    public function render($request, \Throwable $e): Response
    {
        throw $e;
    }

    public function renderForConsole($output, \Throwable $e): void
    {
        throw $e;
    }
}
