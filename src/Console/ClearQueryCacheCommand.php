<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Console;

use Illuminate\Console\Command;
use Nuwave\Lighthouse\Cache\QueryCache;

class ClearQueryCacheCommand extends Command
{
    protected $name = 'lighthouse:clear-query-cache';

    protected $description = 'Clears the file based GraphQL query cache.';

    public function handle(QueryCache $cache): void
    {
        $cache->clearFileCache();

        $this->info('GraphQL query cache deleted.');
    }
}
