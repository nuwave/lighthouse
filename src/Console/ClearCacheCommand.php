<?php

namespace Nuwave\Lighthouse\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\Filesystem;
use Nuwave\Lighthouse\Exceptions\UnknownCacheVersionException;

class ClearCacheCommand extends Command
{
    /**
     * TODO remove once we require Laravel 6 which allows $this->call(ClearCacheCommand::class).
     */
    const NAME = 'lighthouse:clear-cache';

    protected $name = self::NAME;

    protected $description = 'Clear the GraphQL schema cache.';

    public function handle(ConfigRepository $config): void
    {
        $version = $config->get('lighthouse.cache.version', 1);
        switch ($version) {
            case 1:
                /** @var \Illuminate\Contracts\Cache\Factory $cacheFactory */
                $cacheFactory = app(CacheFactory::class);

                $cacheFactory
                    ->store($config->get('lighthouse.cache.store'))
                    ->forget($config->get('lighthouse.cache.key'));
                break;
            case 2:
                /** @var \Illuminate\Filesystem\Filesystem $filesystem */
                $filesystem = app(Filesystem::class);

                $path = $config->get('lighthouse.cache.path')
                    ?? base_path('bootstrap/cache/lighthouse-schema.php');
                $filesystem->delete($path);
                break;
            default:
                throw new UnknownCacheVersionException($version);
        }

        $this->info('GraphQL AST schema cache deleted.');
    }
}
