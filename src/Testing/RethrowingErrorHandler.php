<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Testing;

use GraphQL\Error\Error;
use Nuwave\Lighthouse\Execution\ErrorHandler;

/**
 * Used to essentially disable Lighthouse + graphql-php error handling.
 */
class RethrowingErrorHandler implements ErrorHandler
{
    public function __invoke(?Error $error, \Closure $next): ?array
    {
        if ($error === null) {
            return $next(null);
        }

        throw $error;
    }
}
