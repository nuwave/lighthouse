<?php

namespace Nuwave\Lighthouse\Execution;

use Closure;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\DatabaseManager;

class TransactionalMutations
{
    /**
     * @var \Illuminate\Database\DatabaseManager
     */
    protected $databaseManager;

    /**
     * @var bool
     */
    protected $shouldTransact;

    public function __construct(DatabaseManager $databaseManager, ConfigRepository $configRepository)
    {
        $this->databaseManager = $databaseManager;
        $this->shouldTransact = $configRepository->get('lighthouse.transactional_mutations');
    }

    /**
     * @template TResult
     *
     * @param  \Closure(): TResult $mutation
     *
     * @return TResult
     */
    public function execute(Closure $mutation, ?string $connectionName)
    {
        return $this->shouldTransact
            ? $this->databaseManager
                ->connection($connectionName)
                ->transaction($mutation)
            : $mutation();
    }
}
