<?php

namespace Nuwave\Lighthouse\Console;

use Illuminate\Console\Command;
use GraphQL\Utils\SchemaPrinter;
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
        lighthouse:schema:print
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
            $path = config(
                'lighthouse.schema.output',
                storage_path('app/lighthouse-schema.graphql')
            );

            file_put_contents($path, $schema);

            $this->info(sprintf(
                'Wrote schema to defined file: %s',
                $path
            ));
        } else {
            $this->info($schema);
        }
    }
}
