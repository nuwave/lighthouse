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
        $opcacheTTLHours = $this->option('opcache-ttl-hours');
        if ($opcacheTTLHours !== null && ! is_numeric($opcacheTTLHours)) {
            $this->error('The --opcache-ttl-hours option must be an integer value representing hours.');

            return;
        }

        $opcacheOnly = $this->option('opcache-only');
        if (! is_bool($opcacheOnly)) { // @phpstan-ignore function.alreadyNarrowedType (necessary in some dependency versions)
            $this->error('The --opcache-only option must be a boolean.');

            return;
        }

        $queryCache->clear(
            opcacheTTLHours: $opcacheTTLHours !== null
                ? (int) $opcacheTTLHours
                : null,
            opcacheOnly: $opcacheOnly,
        );

        $this->info('GraphQL query cache deleted.');
    }
}
