<?php

namespace Nuwave\Lighthouse\Console;

use Illuminate\Console\Command;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;

class CacheCommand extends Command
{
    protected $name = 'lighthouse:cache';

    protected $description = 'Compile the GraphQL schema and cache it.';

    public function handle(ASTBuilder $builder): void
    {
        $builder->documentAST();

        $this->info('GraphQL AST schema cache created.');
    }
}
