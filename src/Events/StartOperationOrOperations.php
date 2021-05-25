<?php

namespace Nuwave\Lighthouse\Events;

/**
 * Fires after receiving the parsed operation (single query) or operations (batched query).
 */
class StartOperationOrOperations
{
    /**
     * One or multiple parsed GraphQL operations.
     *
     * @var \GraphQL\Server\OperationParams|array<int, \GraphQL\Server\OperationParams>
     */
    public $operationOrOperations;

    /**
     * @param  \GraphQL\Server\OperationParams|array<int, \GraphQL\Server\OperationParams>  $operationOrOperations
     */
    public function __construct($operationOrOperations)
    {
        $this->operationOrOperations = $operationOrOperations;
    }
}
