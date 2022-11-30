<?php

namespace Nuwave\Lighthouse\Console;

use GraphQL\Type\Introspection;
use GraphQL\Type\Schema;
use GraphQL\Utils\SchemaPrinter;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Nuwave\Lighthouse\Federation\FederationPrinter;
use Nuwave\Lighthouse\Schema\AST\ASTCache;
use Nuwave\Lighthouse\Schema\SchemaBuilder;

class PrintSchemaCommand extends Command
{
    public const GRAPHQL_FILENAME = 'lighthouse-schema.graphql';
    public const GRAPHQL_FEDERATION_FILENAME = 'lighthouse-schema-federation.graphql';
    public const JSON_FILENAME = 'lighthouse-schema.json';

    protected $signature = <<<'SIGNATURE'
lighthouse:print-schema
{--W|write : Write the output to a file}
{--json : Output JSON instead of GraphQL SDL}
{--federation : Include federation directives and exclude federation spec additions, like _service.sdl}
SIGNATURE;

    protected $description = 'Compile the GraphQL schema and print the result.';

    public function handle(ASTCache $cache, Filesystem $storage, SchemaBuilder $schemaBuilder): void
    {
        // Clear the cache so this always gets the current schema
        $cache->clear();

        $schema = $schemaBuilder->schema();

        if ($this->option('federation')) {
            if ($this->option('json')) {
                $this->error('--json option is not supported with --federation');

                return;
            }

            $filename = self::GRAPHQL_FEDERATION_FILENAME;
            $schemaString = FederationPrinter::print($schema);
        } elseif ($this->option('json')) {
            $filename = self::JSON_FILENAME;
            $schemaString = $this->toJson($schema);
        } else {
            $filename = self::GRAPHQL_FILENAME;
            $schemaString = SchemaPrinter::doPrint($schema);
        }

        if ($this->option('write')) {
            $storage->put($filename, $schemaString);
            $this->info("Wrote schema to the default file storage (usually storage/app) as {$filename}.");
        } else {
            $this->info($schemaString);
        }
    }

    protected function toJson(Schema $schema): string
    {
        $introspectionResult = Introspection::fromSchema($schema);
        if (null === $introspectionResult) {
            throw new \Exception(
                <<<'MESSAGE'
Did not receive a valid introspection result.
Check if your schema is correct with:

    php artisan lighthouse:validate-schema

MESSAGE
            );
        }

        return \Safe\json_encode($introspectionResult);
    }
}
