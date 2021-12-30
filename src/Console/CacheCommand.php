<?php

namespace Nuwave\Lighthouse\Console;

use Illuminate\Console\Command;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\AST\ASTCache;

class CacheCommand extends Command
{
    protected $name = 'lighthouse:cache';

    protected $description = 'Compile the GraphQL schema and cache it.';

    public function handle(ASTBuilder $builder, ASTCache $cache): void
    {
        $cache->set($builder->build());

        $this->info('GraphQL schema cache created.');
    }
}
