<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution;

use GraphQL\Error\Error;
use Illuminate\Auth\Access\AuthorizationException as LaravelAuthorizationException;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

/**
 * Wrap native Laravel authorization exceptions, adding structured data to extensions.
 */
class AuthorizationErrorHandler implements ErrorHandler
{
    public function __invoke(?Error $error, \Closure $next): ?array
    {
        if ($error === null) {
            return $next(null);
        }

        $underlyingException = $error->getPrevious();
        if ($underlyingException instanceof LaravelAuthorizationException) {
            return $next(new Error(
                $error->getMessage(),
                $error->getNodes(),
                $error->getSource(),
                $error->getPositions(),
                $error->getPath(),
                AuthorizationException::fromLaravel($underlyingException),
            ));
        }

        return $next($error);
    }
}
