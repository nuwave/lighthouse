<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution;

use GraphQL\Error\Error;

/**
 * Instantiated through the container, once per query.
 *
 * @api
 */
interface ErrorHandler
{
    /**
     * Called with each GraphQL error, allows doing anything with them.
     *
     * Multiple such handlers may be registered as an array in the config.
     * Always call $next($error) to keep the pipeline going.
     * Returning null discards the error.
     *
     * @return array<string, mixed>|null
     */
    public function __invoke(?Error $error, \Closure $next): ?array;
}
