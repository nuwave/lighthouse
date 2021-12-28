<?php

namespace Nuwave\Lighthouse\Testing;

use Closure;
use GraphQL\Error\Error;
use Nuwave\Lighthouse\Execution\ErrorHandler;

/**
 * Used to essentially disable Lighthouse + graphql-php error handling.
 */
class RethrowingErrorHandler implements ErrorHandler
{
    public function __invoke(?Error $error, Closure $next): ?array
    {
        if (null === $error) {
            return $next(null);
        }

        throw $error;
    }
}
