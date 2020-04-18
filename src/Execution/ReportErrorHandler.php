<?php

namespace Nuwave\Lighthouse\Execution;

use Closure;
use GraphQL\Error\Error;
use Illuminate\Contracts\Debug\ExceptionHandler;

/**
 * Report errors through the default exception handler configured in Laravel.
 */
class ReportErrorHandler implements ErrorHandler
{
    public static function handle(Error $error, Closure $next): array
    {
        /** @var \Illuminate\Contracts\Debug\ExceptionHandler $reporter */
        $reporter = app(ExceptionHandler::class);
        $reporter->report($error);

        return $next($error);
    }
}
