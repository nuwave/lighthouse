<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\DatabaseManager;

class TransactionalMutations
{
    protected bool $shouldTransact;

    public function __construct(
        protected DatabaseManager $databaseManager,
        ConfigRepository $configRepository,
    ) {
        $this->shouldTransact = $configRepository->get('lighthouse.transactional_mutations');
    }

    /**
     * @template TResult
     *
     * @param  \Closure(): TResult  $mutation
     *
     * @return TResult
     */
    public function execute(\Closure $mutation, ?string $connectionName)
    {
        return $this->shouldTransact
            ? $this->databaseManager
                ->connection($connectionName)
                ->transaction($mutation)
            : $mutation();
    }
}
