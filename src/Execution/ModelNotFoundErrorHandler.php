<?php

namespace Nuwave\Lighthouse\Execution;

use Closure;
use GraphQL\Error\Error;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Wrap model not found exceptions.
 */
class ModelNotFoundErrorHandler implements ErrorHandler
{
    public function __invoke(?Error $error, Closure $next): ?array
    {
        if (null === $error) {
            return $next(null);
        }

        $previous = $error->getPrevious();

        if ($previous instanceof ModelNotFoundException) {
            return $next(new Error($previous->getMessage()));
        }

        return $next($error);
    }
}
