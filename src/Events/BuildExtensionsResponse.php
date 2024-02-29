<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Events;

use GraphQL\Executor\ExecutionResult;

/**
 * Fires after a query was resolved.
 *
 * Listeners may return a @see \Nuwave\Lighthouse\Execution\ExtensionsResponse
 * to include in the response.
 */
class BuildExtensionsResponse
{
    public function __construct(
        /** The result of resolving a single operation. */
        public ExecutionResult $result,
    ) {}
}
