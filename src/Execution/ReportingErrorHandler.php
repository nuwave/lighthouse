<?php

namespace Nuwave\Lighthouse\Execution;

use Closure;
use GraphQL\Error\Error;
use Illuminate\Contracts\Debug\ExceptionHandler;

/**
 * Report errors through the default exception handler configured in Laravel.
 */
class ReportingErrorHandler implements ErrorHandler
{
    public static function handle(Error $error, Closure $next): array
    {
        // Client-safe errors are assumed to be something that a client can handle
        // or is expected to happen, e.g. wrong syntax, authentication or validation
        if ($error->isClientSafe()) {
            return $next($error);
        }

        // TODO inject through constructor once handle is non-static
        /** @var \Illuminate\Contracts\Debug\ExceptionHandler $reporter */
        $reporter = app(ExceptionHandler::class);
        $reporter->report($error->getPrevious()); // @phpstan-ignore-line TODO remove when supporting Laravel 7 and upwards

        return $next($error);
    }
}
