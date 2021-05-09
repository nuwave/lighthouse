<?php

namespace Nuwave\Lighthouse\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\File;

class ClearCacheCommand extends Command
{
    protected $name = 'lighthouse:clear-cache';

    protected $description = 'Clear the GraphQL schema cache.';

    public function handle(ConfigRepository $config): void
    {
        $path = $config->get('lighthouse.cache.path') ?? base_path('bootstrap/cache/lighthouse-schema.php');
        File::delete($path);

        $this->info('GraphQL AST schema cache deleted.');
    }
}
