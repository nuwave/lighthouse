<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution;

use GraphQL\Error\Error;
use Illuminate\Contracts\Debug\ExceptionHandler;

/**
 * Report errors through the default exception handler configured in Laravel.
 */
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

        // Client-safe errors are assumed to be something that a client can handle
        // or is expected to happen, e.g. wrong syntax, authentication or validation
        if ($error->isClientSafe()) {
            return $next($error);
        }

        $previous = $error->getPrevious();
        if ($previous !== null) {
            $this->exceptionHandler->report($previous);
        }

        return $next($error);
    }
}
