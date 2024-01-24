<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Console;

use GraphQL\Type\Introspection;
use GraphQL\Utils\SchemaPrinter;
use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemManager;
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
{--D|disk= : The disk to write the file to}
{--json : Output JSON instead of GraphQL SDL}
{--federation : Include federation directives and exclude federation spec additions, like _service.sdl}
SIGNATURE;

    protected $description = 'Compile the GraphQL schema and print the result.';

    public function handle(ASTCache $cache, FilesystemManager $filesystemManager, SchemaBuilder $schemaBuilder): void
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
            $schemaString = \Safe\json_encode(Introspection::fromSchema($schema));
        } else {
            $filename = self::GRAPHQL_FILENAME;
            $schemaString = SchemaPrinter::doPrint($schema);
        }

        if ($this->option('write')) {
            $disk = $this->option('disk');
            if (! is_string($disk) && ! is_null($disk)) { // @phpstan-ignore-line can be array
                $diskType = gettype($disk);
                $this->error("Expected option disk to be string or null, got: {$diskType}.");

                return;
            }

            $filesystemManager->disk($disk)->put($filename, $schemaString);
            $this->info("Wrote schema to disk ({$disk}) as {$filename}.");
        } else {
            $this->info($schemaString);
        }
    }
}
