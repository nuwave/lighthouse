<?php

namespace Nuwave\Lighthouse\Events;

use Carbon\Carbon;
use GraphQL\Server\OperationParams;

/**
 * Fires right before resolving an individual query.
 *
 * Might happen multiple times in a single request if
 * query batching is used.
 */
class StartExecution
{
    /**
     * @var \GraphQL\Server\OperationParams
     */
    public $operationParams;

    /**
     * The point in time when the query execution started.
     *
     * @var \Carbon\Carbon
     */
    public $moment;

    public function __construct(OperationParams $operationParams)
    {
        $this->operationParams = $operationParams;
        $this->moment = Carbon::now();
    }
}
