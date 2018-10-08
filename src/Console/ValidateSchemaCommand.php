<?php

namespace Nuwave\Lighthouse\Console;

use Nuwave\Lighthouse\GraphQL;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository;
use Nuwave\Lighthouse\Exceptions\ParseException;
use Nuwave\Lighthouse\Exceptions\DirectiveException;

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
    protected $description = 'Validate the defined schema.';
    
    /**
     * Execute the console command.
     *
     * @param Repository $cache
     * @param GraphQL $graphQL
     *
     * @throws DirectiveException
     * @throws ParseException
     */
    public function handle(Repository $cache, GraphQL $graphQL)
    {
        // Clear the cache so this always validates the current schema
        $cache->forget(config('lighthouse.cache.key'));

        $graphQL->prepSchema()->assertValid();

        $this->info('The defined schema is valid.');
    }
}
