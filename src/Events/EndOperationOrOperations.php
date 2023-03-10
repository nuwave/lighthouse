<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Events;

/**
 * Fires after resolving all operations.
 */
class EndOperationOrOperations
{
    public function __construct(
        /**
         * The result of either a single or multiple operations.
         *
         * @var array<string, mixed>|array<int, array<string, mixed>> $resultOrResults
         */
        public array $resultOrResults,
    ) {}
}
