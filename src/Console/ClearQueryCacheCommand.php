<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Console;

use Illuminate\Console\Command;
use Nuwave\Lighthouse\Cache\QueryCache;

class ClearQueryCacheCommand extends Command
{
    protected $signature = <<<'SIGNATURE'
lighthouse:clear-query-cache
{--opcache-ttl-hours= : Clear only OPcache files older than the given number of hours}
{--opcache-only : Clear only OPcache files, ignoring the cache store}
SIGNATURE;

    protected $description = 'Clears the file based GraphQL query cache.';

    public function handle(QueryCache $queryCache): void
    {
        $queryCache->clear(
            opcacheTTLHours: ($hours = $this->option('opcache-ttl-hours'))
                ? (int) $hours
                : null,
            opcacheOnly: $this->option('opcache-only'),
        );

        $this->info('GraphQL query cache deleted.');
    }
}
