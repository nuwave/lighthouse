<?php

namespace Nuwave\Lighthouse\Execution;

use Closure;
use GraphQL\Error\Error;

interface ErrorHandler
{
    /**
     * This function receives all GraphQL errors and may alter them or do something else with them.
     *
     * Always call $next($error) to keep the Pipeline going. Multiple such Handlers may be registered
     * as an array in the config.
     *
     * @param  \GraphQL\Error\Error  $error
     * @param  \Closure  $next
     * @return array
     */
    public static function handle(Error $error, Closure $next): array;
}
