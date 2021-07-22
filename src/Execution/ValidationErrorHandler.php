<?php

namespace Nuwave\Lighthouse\Execution;

use Closure;
use GraphQL\Error\Error;
use Illuminate\Validation\ValidationException as LaravelValidationException;
use Nuwave\Lighthouse\Exceptions\ValidationException;

/**
 * Wrap native Laravel validation exceptions, adding structured data to extensions.
 */
class ValidationErrorHandler implements ErrorHandler
{
    public function __invoke(?Error $error, Closure $next): ?array
    {
        if ($error === null) {
            return $next(null);
        }

        $underlyingException = $error->getPrevious();
        if ($underlyingException instanceof LaravelValidationException) {
            return $next(ValidationException::fromLaravel($underlyingException));
        }

        return $next($error);
    }
}
