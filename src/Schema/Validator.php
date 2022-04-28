<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Type\Schema;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Nuwave\Lighthouse\Events\ValidateSchema;
use Nuwave\Lighthouse\Schema\AST\ASTCache;
use Nuwave\Lighthouse\Schema\Factories\DirectiveFactory;

class Validator
{
    /**
     * @var \Nuwave\Lighthouse\Schema\AST\ASTCache
     */
    protected $cache;

    /**
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    protected $eventsDispatcher;

    /**
     * @var \Nuwave\Lighthouse\Schema\SchemaBuilder
     */
    protected $schemaBuilder;

    /**
     * @var \Nuwave\Lighthouse\Schema\DirectiveLocator
     */
    protected $directiveLocator;

    /**
     * @var \Nuwave\Lighthouse\Schema\TypeRegistry
     */
    protected $typeRegistry;

    public function __construct(
        ASTCache $cache,
        EventsDispatcher $eventsDispatcher,
        SchemaBuilder $schemaBuilder,
        DirectiveLocator $directiveLocator,
        TypeRegistry $typeRegistry
    ) {
        $this->cache = $cache;
        $this->eventsDispatcher = $eventsDispatcher;
        $this->schemaBuilder = $schemaBuilder;
        $this->directiveLocator = $directiveLocator;
        $this->typeRegistry = $typeRegistry;
    }

    public function validate(): void
    {
        // Clear the cache so this always validates the current schema
        $this->cache->clear();

        $originalSchema = $this->schemaBuilder->schema();
        $schemaConfig = $originalSchema->getConfig();

        // We add schema directive definitions only here, since it is very slow
        $directiveFactory = new DirectiveFactory(
            new FallbackTypeNodeConverter($this->typeRegistry)
        );
        foreach ($this->directiveLocator->definitions() as $directiveDefinition) {
            // TODO consider a solution that feels less hacky
            if ('deprecated' !== $directiveDefinition->name->value) {
                $schemaConfig->directives[] = $directiveFactory->handle($directiveDefinition);
            }
        }

        $schema = new Schema($schemaConfig);
        $schema->assertValid();

        // Allow plugins to do their own schema validations
        $this->eventsDispatcher->dispatch(
            new ValidateSchema($schema)
        );
    }
}
