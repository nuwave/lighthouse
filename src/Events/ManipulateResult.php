<?php

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
    /**
     * The result of resolving an individual query.
     *
     * @var \GraphQL\Executor\ExecutionResult
     */
    public $result;

    /**
     * ManipulateResult constructor.
     *
     * @param  \GraphQL\Executor\ExecutionResult  $result
     * @return void
     */
    public function __construct(ExecutionResult &$result)
    {
        $this->result = $result;
    }
}
