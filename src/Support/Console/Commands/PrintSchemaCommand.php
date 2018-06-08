<?php

namespace Nuwave\Lighthouse\Support\Console\Commands;

use GraphQL\Utils\SchemaPrinter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Nuwave\Lighthouse\GraphQL;

class PrintSchemaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lighthouse:print-schema';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Print the resulting schema.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Clear the cache so this always gets the current schema
        Cache::forget(GraphQL::AST_CACHE_KEY);

        $this->info(SchemaPrinter::doPrint(graphql()->retrieveSchema()));
    }
}
