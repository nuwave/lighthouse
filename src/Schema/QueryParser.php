<?php

namespace Nuwave\Relay\Schema;

use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use GraphQL\Language\AST\Field as QueryField;
use Nuwave\Relay\Schema\SchemaBuilder;

class QueryParser
{
    /**
     * Instance of schema builder.
     *
     * @var SchemaBuilder
     */
    protected $schema;

    /**
     * Request query.
     *
     * @var string
     */
    protected $query;

    /**
     * Create new instance of queyr parser.
     *
     * @param SchemaBuilder $schema
     */
    public function __construct(SchemaBuilder $schema, $query = '')
    {
        $this->schema = $schema;
        $this->query = $query;
    }

    /**
     * Parse middleware from query.
     *
     * @param  string $query
     * @param  string $operation
     * @return self
     */
    public function middleware()
    {
        $ast = Parser::parse(new Source($this->query));

        if (! isset($ast->definitions[0])) {
            return null;
        }

        $d = $ast->definitions[0];
        $operation = $d->operation ?: 'query';
        $selectionSet = $d->selectionSet->selections;

        return $this->extractMiddleware($selectionSet, $operation);
    }

    /**
     * Extract middleware from query.
     *
     * @param  array $selectionSet
     * @param  string $operation
     * @return \Illuminate\Support\Collection
     */
    protected function extractMiddleware(array $selectionSet = [], $operation = '')
    {
        return collect($selectionSet)->flatMap(function ($selection) use ($operation) {
            if ($this->isField($selection)) {
                if ($field = $this->getField($selection->name->value, $operation)) {
                    return $field->middleware;
                }
            }

            return [];
        });
    }

    /**
     * Determine if selection is a Field
     *
     * @param  mixed  $selection
     * @return boolean
     */
    public function isField($selection)
    {
        return is_object($selection) && $selection instanceof QueryField;
    }

    /**
     * Find field by operation and name.
     *
     * @param  string $name
     * @param  string $operation
     * @return array
     */
    public function getField($name, $operation = 'query')
    {
        if ($operation == 'mutation') {
            return $this->schema->getMutationRegistrar()->get($name);
        } elseif ($operation == 'type') {
            return $this->schema->getTypeRegistrar()->get($name);
        }

        return $this->schema->getQueryRegistrar()->get($name);
    }
}
