<?php

namespace Nuwave\Lighthouse\Execution;

use Closure;
use GraphQL\Error\Error;

/**
 * Instantiated through the container, once per query.
 */
interface ErrorHandler
{
    /**
     * Called with each GraphQL error, allows doing anything with them.
     *
     * Multiple such Handlers may be registered as an array in the config.
     * Always call $next($error) to keep the Pipeline going.
     * Returning null discards the error.
     *
     * @return array<string, mixed>|null
     */
    public function __invoke(?Error $error, Closure $next): ?array;
}
