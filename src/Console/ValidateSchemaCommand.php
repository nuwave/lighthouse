<?php

namespace Nuwave\Lighthouse\Console;

use Nuwave\Lighthouse\GraphQL;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository;

class ValidateSchemaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lighthouse:validate-schema';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate the GraphQL schema definition.';

    /**
     * Execute the console command.
     *
     * @param  \Illuminate\Contracts\Cache\Repository  $cache
     * @param  \Nuwave\Lighthouse\GraphQL  $graphQL
     * @return void
     */
    public function handle(Repository $cache, GraphQL $graphQL): void
    {
        // Clear the cache so this always validates the current schema
        $cache->forget(config('lighthouse.cache.key'));

        $graphQL->prepSchema()->assertValid();

        $this->info('The defined schema is valid.');
    }
}
