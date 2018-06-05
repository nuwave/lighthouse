<?php


namespace Nuwave\Lighthouse;


class GraphQL
{
    /** @var DirectiveRegistry */
    public $directiveRegistry;

    /** @var Schema */
    public $schema;

    /**
     * GraphQL constructor.
     */
    public function __construct()
    {
        $this->directiveRegistry = app(DirectiveRegistry::class);
    }


    public function build(string $schema) : Schema
    {
        return $this->schema = app(SchemaBuilder::class)->buildFromTypeLanguage($schema);
    }

    public function execute(string $query)
    {
        return app(Executor::class)->execute($this->schema(), $query);
    }

    public function directives() : DirectiveRegistry
    {
        return $this->directiveRegistry;
    }

    public function schema() : ?schema
    {
        return $this->schema;
    }
}