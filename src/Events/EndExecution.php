<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Events;

use GraphQL\Executor\ExecutionResult;
use Illuminate\Support\Carbon;

/**
 * Fires after resolving a single operation.
 */
class EndExecution
{
    /** The point in time when the result was ready. */
    public Carbon $moment;

    public function __construct(
        /**
         * The result of resolving a single operation.
         */
        public ExecutionResult $result,
    ) {
        $this->moment = Carbon::now();
    }
}
