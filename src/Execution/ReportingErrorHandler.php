<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution;

use GraphQL\Error\Error;
use Illuminate\Contracts\Debug\ExceptionHandler;

/** Report non-client-safe errors through the default Laravel exception handler. */
class ReportingErrorHandler implements ErrorHandler
{
    public function __construct(
        protected ExceptionHandler $exceptionHandler,
    ) {}

    public function __invoke(?Error $error, \Closure $next): ?array
    {
        if ($error === null) {
            return $next(null);
        }

        // Client-safe errors are assumed to be something that:
        // - a client can understand and handle
        // - were caused by client misuse, e.g. wrong syntax, authentication, validation
        // Thus, they are typically not actionable for server developers.
        if ($error->isClientSafe()) {
            return $next($error);
        }

        $this->exceptionHandler->report(
            $error->getPrevious() ?? $error,
        );

        return $next($error);
    }
}
