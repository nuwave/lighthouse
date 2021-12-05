<?php

namespace Nuwave\Lighthouse\Events;

use GraphQL\Executor\ExecutionResult;
use Illuminate\Support\Carbon;

/**
 * Fires after resolving a single operation.
 */
class EndExecution
{
    /**
     * The result of resolving a single operation.
     *
     * @var \GraphQL\Executor\ExecutionResult
     */
    public $result;

    /**
     * The point in time when the result was ready.
     *
     * @var \Illuminate\Support\Carbon
     */
    public $moment;

    public function __construct(ExecutionResult $result)
    {
        $this->result = $result;
        $this->moment = Carbon::now();
    }
}
