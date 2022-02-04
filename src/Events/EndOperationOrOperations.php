<?php

namespace Nuwave\Lighthouse\Events;

/**
 * Fires after resolving all operations.
 */
class EndOperationOrOperations
{
    /**
     * The result of either a single or multiple operations.
     *
     * @var array<string, mixed>|array<int, array<string, mixed>>
     */
    public $resultOrResults;

    /**
     * @param  array<string, mixed>|array<int, array<string, mixed>>  $resultOrResults
     */
    public function __construct(array $resultOrResults)
    {
        $this->resultOrResults = $resultOrResults;
    }
}
