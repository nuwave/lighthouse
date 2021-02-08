<?php

namespace Nuwave\Lighthouse\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

class ClearCacheCommand extends Command
{
    protected $name = 'lighthouse:clear-cache';

    protected $description = 'Clear the GraphQL schema cache.';

    public function handle(CacheRepository $cache, ConfigRepository $config): void
    {
        $cache->forget(
            $config->get('lighthouse.cache.key')
        );

        $this->info('GraphQL AST schema cache deleted.');
    }
}
