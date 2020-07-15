<?php

namespace Nuwave\Lighthouse\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Nuwave\Lighthouse\GraphQL;

class ValidateSchemaCommand extends Command
{
    protected $name = 'lighthouse:validate-schema';

    protected $description = 'Validate the GraphQL schema definition.';

    public function handle(CacheRepository $cache, ConfigRepository $config, GraphQL $graphQL): void
    {
        // Clear the cache so this always validates the current schema
        $cache->forget(
            $config->get('lighthouse.cache.key')
        );

        $schema = $graphQL->prepSchema();
        $schema->assertValid();

        $this->info('The defined schema is valid.');
    }
}
