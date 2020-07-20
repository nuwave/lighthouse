<?php

namespace Nuwave\Lighthouse\Execution;

use Closure;
use GraphQL\Error\Error;
use Nuwave\Lighthouse\Exceptions\RendersErrorsExtensions;

/**
 * Handle Exceptions that implement Nuwave\Lighthouse\Exceptions\RendersErrorsExtensions
 * and add extra content from them to the 'extensions' key of the Error that is rendered
 * to the User.
 */
class ExtensionErrorHandler implements ErrorHandler
{
    public static function handle(Error $error, Closure $next): array
    {
        $underlyingException = $error->getPrevious();

        if ($underlyingException instanceof RendersErrorsExtensions) {
            // Reconstruct the error, passing in the extensions of the underlying exception
            $error = new Error( // @phpstan-ignore-line TODO remove after graphql-php upgrade
                $error->message,
                $error->nodes,
                $error->getSource(),
                $error->getPositions(),
                $error->getPath(),
                $underlyingException,
                $underlyingException->extensionsContent()
            );
        }

        return $next($error);
    }
}
