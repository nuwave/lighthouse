<?php

namespace Nuwave\Lighthouse\Execution;

use Closure;
use GraphQL\Error\Error;

interface ErrorHandler
{
    /**
     * This function receives all GraphQL errors and may alter them or do something else with them.
     *
     * Multiple such Handlers may be registered as an array in the config.
     * Always call $next($error) to keep the Pipeline going.
     * Returning null discards the error.
     *
     * @return array<string, mixed>|null
     */
    public function handle(Error $error, Closure $next): ?array;
}
