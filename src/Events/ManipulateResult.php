<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Events;

use GraphQL\Executor\ExecutionResult;

/**
 * Fires after resolving each individual query.
 *
 * This gives listeners an easy way to manipulate the query
 * result without worrying about batched execution.
 */
class ManipulateResult
{
    public function __construct(
        /**
         * The result of resolving an individual query.
         */
        public ExecutionResult &$result,
    ) {}
}
