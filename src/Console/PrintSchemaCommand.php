<?php

namespace Nuwave\Lighthouse\Console;

use GraphQL\Type\Introspection;
use GraphQL\Type\Schema;
use GraphQL\Utils\SchemaPrinter;
use Illuminate\Cache\Repository;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Nuwave\Lighthouse\GraphQL;

class PrintSchemaCommand extends Command
{
    public const GRAPHQL_FILENAME = 'lighthouse-schema.graphql';
    public const JSON_FILENAME = 'lighthouse-schema.json';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = '
        lighthouse:print-schema
        {--W|write : Write the output to a file}
        {--json : Output JSON instead of GraphQL SDL}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compile the final GraphQL schema and print the result.';

    /**
     * Execute the console command.
     */
    public function handle(Repository $cache, Filesystem $storage, GraphQL $graphQL): void
    {
        // Clear the cache so this always gets the current schema
        $cache->forget(config('lighthouse.cache.key'));

        $schema = $graphQL->prepSchema();
        if ($this->option('json')) {
            $filename = self::JSON_FILENAME;
            $schemaString = $this->schemaJson($schema);
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

    /**
     * Convert the given schema to a JSON string.
     */
    protected function schemaJson(Schema $schema): string
    {
        // TODO simplify once https://github.com/webonyx/graphql-php/pull/539 is released
        $introspectionResult = \GraphQL\GraphQL::executeQuery(
            $schema,
            Introspection::getIntrospectionQuery()
        );

        // TODO use safe-php
        return json_encode($introspectionResult->data);
    }
}
