<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\ClientDirectives;

use GraphQL\Executor\Values;
use GraphQL\Type\Definition\Directive;
use Illuminate\Container\Container;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\SchemaBuilder;

/**
 * Provides information about where client directives
 * were placed in the query and what arguments were given to them.
 *
 * TODO implement accessors for other locations https://spec.graphql.org/draft/#ExecutableDirectiveLocation
 */
class ClientDirective
{
    protected Directive $definition;

    public function __construct(
        protected string $name,
    ) {}

    /**
     * Get the given values for a client directive.
     *
     * This returns an array of the given arguments for all field nodes.
     * The number of items in the returned result will always be equivalent
     * to the number of field nodes, each having one of the following values:
     * - When a field node does not have the directive on it: null
     * - When the directive is present but has no arguments: []
     * - When the directive is present with arguments: an associative array
     *
     * @return array<array<string, mixed>|null>
     */
    public function forField(ResolveInfo $resolveInfo): array
    {
        $directive = $this->definition();

        $arguments = [];
        foreach ($resolveInfo->fieldNodes as $fieldNode) {
            $arguments[] = Values::getDirectiveValues($directive, $fieldNode, $resolveInfo->variableValues);
        }

        return $arguments;
    }

    protected function definition(): Directive
    {
        if (! isset($this->definition)) {
            $schemaBuilder = Container::getInstance()->make(SchemaBuilder::class);
            $schema = $schemaBuilder->schema();

            return $this->definition = $schema->getDirective($this->name)
                ?? throw new DefinitionException("Missing a schema definition for the client directive {$this->name}.");
        }

        return $this->definition;
    }
}
