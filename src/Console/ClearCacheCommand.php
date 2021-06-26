<?php

namespace Nuwave\Lighthouse\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class ClearCacheCommand extends Command
{
    protected $name = 'lighthouse:clear-cache';

    protected $description = 'Clear the GraphQL schema cache.';

    public function handle(CacheFactory $cacheFactory, ConfigRepository $config): void
    {
        $version = $config->get('lighthouse.cache.version', 1);
        if ($version === 1) {
            $cacheFactory
                ->store($config->get('lighthouse.cache.store'))
                ->forget($config->get('lighthouse.cache.key'));
        } elseif ($version === 2) {
            $path = $config->get('lighthouse.cache.path') ?? base_path('bootstrap/cache/lighthouse-schema.php');
            File::delete($path);
        } else {
            throw new InvalidArgumentException('Unknown cache version.');
        }

        $this->info('GraphQL AST schema cache deleted.');
    }
}
