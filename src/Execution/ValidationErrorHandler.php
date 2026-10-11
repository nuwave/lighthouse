<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution;

use GraphQL\Error\Error;
use Illuminate\Validation\ValidationException as LaravelValidationException;
use Nuwave\Lighthouse\Exceptions\ValidationException;

/**
 * Wrap native Laravel validation exceptions, adding structured data to extensions.
 */
class ValidationErrorHandler implements ErrorHandler
{
    public function __invoke(?Error $error, \Closure $next): ?array
    {
        if ($error === null) {
            return $next(null);
        }

        $underlyingException = $error->getPrevious();
        if ($underlyingException instanceof LaravelValidationException) {
            return $next(new Error(
                $error->getMessage(),
                $error->getNodes(),
                $error->getSource(),
                $error->getPositions(),
                $error->getPath(),
                ValidationException::fromLaravel($underlyingException),
            ));
        }

        return $next($error);
    }
}
