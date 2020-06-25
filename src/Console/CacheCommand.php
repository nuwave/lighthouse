<?php

namespace Nuwave\Lighthouse\Console;

use Illuminate\Console\Command;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;

class CacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lighthouse:cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compile the GraphQL AST cache.';

    /**
     * Execute the console command.
     */
    public function handle(ASTBuilder $builder): void
    {
        $builder->documentAST();

        $this->info('GraphQL AST schema cache created.');
    }
}
