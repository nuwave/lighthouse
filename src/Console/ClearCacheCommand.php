<?php

namespace Nuwave\Lighthouse\Console;

use Illuminate\Console\Command;
use Nuwave\Lighthouse\Schema\AST\ASTCache;

class ClearCacheCommand extends Command
{
    protected $name = 'lighthouse:clear-cache';

    protected $description = 'Clear the GraphQL schema cache.';

    public function handle(ASTCache $cache): void
    {
        $cache->clear();

        $this->info('GraphQL AST schema cache deleted.');
    }
}
