<?php

namespace Nuwave\Lighthouse\ClientDirectives;

use GraphQL\Executor\Values;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\GraphQL;
use Nuwave\Lighthouse\Schema\SchemaBuilder;

/**
 * Provides information about where client directives
 * were placed in the query and what arguments were given to them.
 *
 * TODO implement accessors for other locations https://spec.graphql.org/draft/#ExecutableDirectiveLocation
 */
class ClientDirective
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var \GraphQL\Type\Definition\Directive|null
     */
    protected $definition;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

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

    /**
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    protected function definition(): Directive
    {
        if (null !== $this->definition) {
            return $this->definition;
        }

        /** @var \Nuwave\Lighthouse\Schema\SchemaBuilder $schemaBuilder */
        $schemaBuilder = app(SchemaBuilder::class);
        $schema = $schemaBuilder->schema();

        $definition = $schema->getDirective($this->name);
        if (! $definition instanceof Directive) {
            throw new DefinitionException("Missing a schema definition for the client directive $this->name");
        }

        return $this->definition = $definition;
    }
}
