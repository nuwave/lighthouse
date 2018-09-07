<?php

namespace Nuwave\Lighthouse\Execution;

use GraphQL\Error\Error;
use Nuwave\Lighthouse\Exceptions\RendersErrorsExtensions;

/**
 * Handle Exceptions that implement Nuwave\Lighthouse\Exceptions\RendersErrorsExtensions
 * and add extra content from them to the 'extensions' key of the Error that is rendered
 * to the User.
 */
class ExtensionErrorHandler implements ErrorHandler
{
    public static function handle(Error $error, \Closure $next): array
    {
        $underlyingException = $error->getPrevious();

        if($underlyingException && $underlyingException instanceof RendersErrorsExtensions) {
            // Reconstruct the error, passing in the extensions of the underlying exception
            $error = new Error(
                $error->message,
                $error->nodes,
                $error->getSource(),
                $error->getPositions(),
                $error->getPath(),
                $underlyingException,
                // TODO remove this wrapping as we switch to the new version of webonyx/graphql-php
                // The underlying issue is already resolved there. For now, this fix will do
                // and make sure we return a spec compliant error.
                // https://github.com/webonyx/graphql-php/commit/f4008f0fb2294178fc0ecc3f7a7f71a13b543db1
                ['extensions' => $underlyingException->extensionsContent()]
            );
        }

        return $next($error);
    }
}
