<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution;

use GraphQL\Error\Error;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;

class ReportingErrorHandler implements ErrorHandler
{
    public function __construct(
        protected ExceptionHandler $exceptionHandler,
        protected Repository $config,
    ) {}

    public function __invoke(?Error $error, \Closure $next): ?array
    {
        if ($error === null) {
            return $next(null);
        }

        if ($this->shouldNotReport($error)) {
            return $next($error);
        }

        $this->exceptionHandler->report(
            $error->getPrevious() ?? $error,
        );

        return $next($error);
    }

    private function shouldNotReport(Error $error): bool
    {
        if ($this->config->get('lighthouse.report_client_safe_errors', false)) {
            return false;
        }

        // Client-safe errors are assumed to be something that:
        // - a client can understand and handle
        // - were caused by client misuse, e.g. wrong syntax, authentication, validation
        // Thus, they are typically not actionable for server developers.
        return $error->isClientSafe();
    }
}
