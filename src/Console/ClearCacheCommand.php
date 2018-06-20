<?php

namespace Nuwave\Lighthouse\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lighthouse:clear-cache';
    
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear the cache for the GraphQL AST.';
    
    /**
     * Execute the console command.
     */
    public function handle()
    {
        Cache::forget(config('lighthouse.cache.key'));
        
        $this->info('GraphQL AST schema cache deleted.');
    }
}
