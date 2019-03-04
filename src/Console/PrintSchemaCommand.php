<?php

namespace Nuwave\Lighthouse\Console;

use Illuminate\Console\Command;
use GraphQL\Utils\SchemaPrinter;
use Illuminate\Cache\Repository;
use Nuwave\Lighthouse\Support\Helper;
use Illuminate\Contracts\Filesystem\Filesystem;

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
     *
     * @param  \Illuminate\Cache\Repository  $cache
     * @param  \Illuminate\Contracts\Filesystem\Filesystem  $storage
     * @return void
     */
    public function handle(Repository $cache, Filesystem $storage): void
    {
        // Clear the cache so this always gets the current schema
        $cache->forget(config('lighthouse.cache.key'));

        $schema = SchemaPrinter::doPrint(
            Helper::graphql()->prepSchema()
        );

        if ($this->option('write')) {
            $this->info('Wrote schema to the default file storage (usually storage/app) as "lighthouse-schema.graphql".');
            $storage->put('lighthouse-schema.graphql', $schema);
        } else {
            $this->info($schema);
        }
    }
}
