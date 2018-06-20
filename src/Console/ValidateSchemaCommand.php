<?php

namespace Nuwave\Lighthouse\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

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
     */
    public function handle()
    {
        // Clear the cache so this always validates the current schema
        Cache::forget(config('lighthouse.cache.key'));
        graphql()->prepSchema()->assertValid();
        $this->info('The defined schema is valid.');
    }
}
