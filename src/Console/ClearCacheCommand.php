<?php

namespace Nuwave\Lighthouse\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

class ClearCacheCommand extends Command
{
    protected $name = 'lighthouse:clear-cache';

    protected $description = 'Clear the GraphQL schema cache.';

    public function handle(CacheFactory $cacheFactory, ConfigRepository $config): void
    {
        $cacheFactory
            ->store($config->get('lighthouse.cache.store'))
            ->forget($config->get('lighthouse.cache.key'));

        $this->info('GraphQL AST schema cache deleted.');
    }
}
