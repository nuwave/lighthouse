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
        $previous = $error->getPrevious();

        // If the Error does not wrap another Error, it is related to a client error
        // and shown in the error response anyway, we don't really need to report it
        if ($previous) {
            /** @var \Illuminate\Contracts\Debug\ExceptionHandler $reporter */
            $reporter = app(ExceptionHandler::class);
            $reporter->report($previous);
        }

        return $next($error);
    }
}
