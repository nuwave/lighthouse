<?php

namespace Nuwave\Lighthouse\Support\Console\Commands;

use Illuminate\Console\Command;
use Nuwave\Lighthouse\Support\Cache\FileStore;

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
    protected $description = 'Cache Eloquent Types.';

    /**
     * Cache manager.
     *
     * @var \Nuwave\Lighthouse\Support\Cache\FileStore
     */
    protected $cache;

    /**
     * Create new instance of cache command.
     *
     * @param FileStore $cache
     */
    public function __construct(FileStore $cache)
    {
        parent::__construct();

        $this->cache = $cache;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->cache->flush();

        app('graphql')->schema();

        $this->info('Eloquent Types successfully cached.');
    }
}
