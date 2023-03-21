<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Type\Schema;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Nuwave\Lighthouse\Events\ValidateSchema;
use Nuwave\Lighthouse\Schema\AST\ASTCache;
use Nuwave\Lighthouse\Schema\AST\FallbackTypeNodeConverter;
use Nuwave\Lighthouse\Schema\Factories\DirectiveFactory;

class Validator
{
    public function __construct(
        protected ASTCache $cache,
        protected EventsDispatcher $eventsDispatcher,
        protected SchemaBuilder $schemaBuilder,
        protected DirectiveLocator $directiveLocator,
        protected TypeRegistry $typeRegistry,
    ) {}

    public function validate(): void
    {
        // Clear the cache so this always validates the current schema
        $this->cache->clear();

        $originalSchema = $this->schemaBuilder->schema();
        $schemaConfig = $originalSchema->getConfig();

        // We add schema directive definitions only here, since it is very slow
        $directiveFactory = new DirectiveFactory(
            new FallbackTypeNodeConverter($this->typeRegistry),
        );
        foreach ($this->directiveLocator->definitions() as $directiveDefinition) {
            // TODO consider a solution that feels less hacky
            if ($directiveDefinition->name->value !== 'deprecated') {
                $schemaConfig->directives[] = $directiveFactory->handle($directiveDefinition);
            }
        }

        $schema = new Schema($schemaConfig);
        $schema->assertValid();

        // Allow plugins to do their own schema validations
        $this->eventsDispatcher->dispatch(
            new ValidateSchema($schema),
        );
    }
}
