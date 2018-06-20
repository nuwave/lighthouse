<?php

namespace Nuwave\Lighthouse\Console;

use GraphQL\Utils\SchemaPrinter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class PrintSchemaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = '
        lighthouse:print-schema
        {--W|write : Write the output to a file}
    ';
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
        Cache::forget(config('lighthouse.cache.key'));
        $schema = SchemaPrinter::doPrint(
            graphql()->prepSchema()
        );
        if ($this->option('write')) {
            $this->info('Wrote schema to the default file storage (usually storage/app)');
            Storage::put('lighthouse-schema.graphqls', $schema);
        } else {
            $this->info($schema);
        }
    }
}
