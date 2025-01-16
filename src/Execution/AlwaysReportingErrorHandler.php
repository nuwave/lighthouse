<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution;

use GraphQL\Error\Error;
use Illuminate\Contracts\Debug\ExceptionHandler;

/** Report all errors through the default Laravel exception handler. */
class AlwaysReportingErrorHandler implements ErrorHandler
{
    public function __construct(
        protected ExceptionHandler $exceptionHandler,
    ) {}

    public function __invoke(?Error $error, \Closure $next): ?array
    {
        if ($error === null) {
            return $next(null);
        }

        $this->exceptionHandler->report(
            $error->getPrevious() ?? $error,
        );

        return $next($error);
    }
}
