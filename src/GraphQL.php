<?php


namespace Nuwave\Lighthouse;


use Nuwave\Lighthouse\Schema\DirectiveRegistry;
use Nuwave\Lighthouse\Schema\Schema;
use Nuwave\Lighthouse\Support\Contracts\SchemaBuilder;

class GraphQL
{
    /** @var DirectiveRegistry */
    public $directiveRegistry;

    /** @var Schema */
    public $schema;

    public $schemaBuilder;

    public $executor;

    /**
     * GraphQL constructor.
     *
     * @param SchemaBuilder $schemaBuilder
     * @param Executor $executor
     * @param DirectiveRegistry $directiveRegistry
     */
    public function __construct(SchemaBuilder $schemaBuilder, Executor $executor, DirectiveRegistry $directiveRegistry)
    {
        $this->schemaBuilder = $schemaBuilder;
        $this->executor = $executor;
        $this->directiveRegistry = $directiveRegistry;
    }

    public function build(string $schema) : Schema
    {
        return $this->schema = $this->schemaBuilder->buildFromTypeLanguage($schema);
    }

    public function execute(string $query)
    {
        return $this->executor->execute($this->schema(), $query);
    }

    public function directives() : DirectiveRegistry
    {
        return $this->directiveRegistry;
    }

    public function schema() : ?schema
    {
        return $this->schema;
    }

    public function setSchema(Schema $schema)
    {
        $this->schema = $schema;
    }
}
