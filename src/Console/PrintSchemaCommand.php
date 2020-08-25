<?php

namespace Nuwave\Lighthouse\Console;

use GraphQL\Type\Introspection;
use GraphQL\Type\Schema;
use GraphQL\Utils\SchemaPrinter;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Filesystem\Filesystem;
use Nuwave\Lighthouse\GraphQL;

class PrintSchemaCommand extends Command
{
    public const GRAPHQL_FILENAME = 'lighthouse-schema.graphql';
    public const JSON_FILENAME = 'lighthouse-schema.json';

    protected $signature = <<<'SIGNATURE'
lighthouse:print-schema
{--W|write : Write the output to a file}
{--json : Output JSON instead of GraphQL SDL}
SIGNATURE;

    protected $description = 'Compile the GraphQL schema and print the result.';

    public function handle(CacheRepository $cache, ConfigRepository $config, Filesystem $storage, GraphQL $graphQL): void
    {
        // Clear the cache so this always gets the current schema
        $cache->forget(
            $config->get('lighthouse.cache.key')
        );

        $schema = $graphQL->prepSchema();
        if ($this->option('json')) {
            $filename = self::JSON_FILENAME;
            $schemaString = $this->toJson($schema);
        } else {
            $filename = self::GRAPHQL_FILENAME;
            $schemaString = SchemaPrinter::doPrint($schema);
        }

        if ($this->option('write')) {
            $storage->put($filename, $schemaString);
            $this->info('Wrote schema to the default file storage (usually storage/app) as "'.$filename.'".');
        } else {
            $this->info($schemaString);
        }
    }

    protected function toJson(Schema $schema): string
    {
        $introspectionResult = Introspection::fromSchema($schema);
        if ($introspectionResult === null) {
            throw new \Exception(<<<'MESSAGE'
Did not receive a valid introspection result.
Check if your schema is correct with:

    php artisan lighthouse:validate-schema

MESSAGE
);
        }

        return \Safe\json_encode($introspectionResult);
    }
}
