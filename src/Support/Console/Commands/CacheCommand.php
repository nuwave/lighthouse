<?php

namespace Nuwave\Lighthouse\Support\Console\Commands;

use Illuminate\Console\Command;

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
    protected $description = 'Cache GraphQL AST.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if (! config('lighthouse.cache')) {
            $this->error('The `lighthouse.cache` setting must be set to a file path.');

            return;
        }

        $schema = graphql()->stitcher()->stitch(
            config('lighthouse.global_id_field', '_id'),
            config('lighthouse.schema.register')
        );

        graphql()->cache()->set($schema);

        $this->info('GraphQL AST successfully cached.');
    }
}
