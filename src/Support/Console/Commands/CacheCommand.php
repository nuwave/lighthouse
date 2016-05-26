<?php

namespace Nuwave\Relay\Support\Console\Commands;

use Illuminate\Console\Command;
use Nuwave\Relay\Support\SchemaGenerator;
use Nuwave\Relay\Support\Cache\FileStore;

class CacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'relay:cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cache Eloquent Types.';

    /**
     * Cache manager.
     *
     * @var \Nuwave\Relay\Support\Cache\FileStore
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
