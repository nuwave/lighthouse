<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Console;

use Illuminate\Console\Command;
use Nuwave\Lighthouse\Schema\AST\ASTCache;

class ClearSchemaCacheCommand extends Command
{
    protected $name = 'lighthouse:clear-schema-cache';

    protected $description = 'Clear the GraphQL schema cache.';

    public function handle(ASTCache $cache): void
    {
        $cache->clear();

        $this->info('GraphQL AST schema cache deleted.');
    }
}
