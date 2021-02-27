<?php

namespace Nuwave\Lighthouse\Console;

use GraphQL\Type\Schema;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Nuwave\Lighthouse\Events\ValidateSchema;
use Nuwave\Lighthouse\Schema\DirectiveLocator;
use Nuwave\Lighthouse\Schema\Factories\DirectiveFactory;
use Nuwave\Lighthouse\Schema\FallbackTypeNodeConverter;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
use Nuwave\Lighthouse\Schema\TypeRegistry;

class ValidateSchemaCommand extends Command
{
    protected $name = 'lighthouse:validate-schema';

    protected $description = 'Validate the GraphQL schema definition.';

    public function handle(
        CacheRepository $cache,
        ConfigRepository $config,
        EventsDispatcher $eventsDispatcher,
        SchemaBuilder $schemaBuilder,
        DirectiveLocator $directiveLocator,
        TypeRegistry $typeRegistry
    ): void {
        // Clear the cache so this always validates the current schema
        $cache->forget(
            $config->get('lighthouse.cache.key')
        );

        $originalSchema = $schemaBuilder->schema();
        $schemaConfig = $originalSchema->getConfig();

        // We add schema directive definitions only here, since it is very slow
        $directiveFactory = new DirectiveFactory(
            new FallbackTypeNodeConverter($typeRegistry)
        );
        foreach ($directiveLocator->definitions() as $directiveDefinition) {
            // TODO consider a solution that feels less hacky
            if ($directiveDefinition->name->value !== 'deprecated') {
                $schemaConfig->directives [] = $directiveFactory->handle($directiveDefinition);
            }
        }

        $schema = new Schema($schemaConfig);
        $schema->assertValid();

        // Allow plugins to do their own schema validations
        $eventsDispatcher->dispatch(
            new ValidateSchema($schema)
        );

        $this->info('The defined schema is valid.');
    }
}
