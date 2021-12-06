<?php

namespace Nuwave\Lighthouse\Execution;

use Closure;
use GraphQL\Error\Error;
use Illuminate\Auth\AuthenticationException as LaravelAuthenticationException;
use Nuwave\Lighthouse\Exceptions\AuthenticationException;

/**
 * Wrap native Laravel authentication exceptions, adding structured data to extensions.
 */
class AuthenticationErrorHandler implements ErrorHandler
{
    public function __invoke(?Error $error, Closure $next): ?array
    {
        if (null === $error) {
            return $next(null);
        }

        $underlyingException = $error->getPrevious();
        if ($underlyingException instanceof LaravelAuthenticationException) {
            return $next(new Error(
                $error->getMessage(),
                // @phpstan-ignore-next-line graphql-php and phpstan disagree with themselves
                $error->getNodes(),
                $error->getSource(),
                $error->getPositions(),
                $error->getPath(),
                AuthenticationException::fromLaravel($underlyingException)
            ));
        }

        return $next($error);
    }
}
