<?php
declare(strict_types=1);

namespace Nuwave\Lighthouse\Console;

use Illuminate\Console\Command;
use Nuwave\Lighthouse\Cache\QueryCache;

class ClearQueryCacheCommand extends Command
{
    protected $signature = 'lighthouse:clear-query-cache {--hours=}';

    protected $description = 'Clears the file based GraphQL query cache.';

    public function handle(QueryCache $cache): void
    {
        $cache->clearFileCache(
            ($hours = $this->option('hours'))
                ? intval($hours)
                : null
        );

        $this->info('GraphQL query cache deleted.');
    }
}
