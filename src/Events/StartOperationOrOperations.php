<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Events;

use GraphQL\Server\OperationParams;

/**
 * Fires after receiving the parsed operation (single query) or operations (batched query).
 */
class StartOperationOrOperations
{
    public function __construct(
        /**
         * One or multiple parsed GraphQL operations.
         *
         * @var \GraphQL\Server\OperationParams|array<int, \GraphQL\Server\OperationParams>
         */
        public OperationParams|array $operationOrOperations,
    ) {}
}
