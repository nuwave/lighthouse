<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use GraphQL\Language\AST\Field as QueryField;
use Nuwave\Lighthouse\Schema\Generators\ConnectionGenerator;

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
        $selectionSet = $this->getSelectionSet();
        $operation = $this->getOperation();

        return collect($selectionSet)->flatMap(function ($selection) use ($operation) {
            if ($field = $this->getField($selection, $operation)) {
                return $field->middleware;
            }

            return [];
        });
    }

    /**
     * Get connections requested in query.
     *
     * @return \Illuminate\Support\Collection
     */
    public function connections()
    {
        $selectionSet = $this->getSelectionSet();
        $operation = $this->getOperation();

        return collect($selectionSet)->flatMap(function ($selection) use ($operation) {
            if ($field = $this->getField($selection, $operation)) {
                if (isset($selection->selectionSet) && ! empty($selection->selectionSet->selections)) {
                    return (new ConnectionGenerator)->build(
                        $selection->selectionSet->selections,
                        $selection->name->value
                    );
                }
            }

            return [];
        })->reject(function ($item) {
            return empty($item);
        });
    }

    /**
     * Get selection set from query.
     *
     * @return array
     */
    protected function getSelectionSet()
    {
        $ast = Parser::parse(new Source($this->query));

        if (! isset($ast->definitions[0])) {
            return [];
        }

        $d = $ast->definitions[0];

        return $d->selectionSet->selections;
    }

    /**
     * Get operation from query.
     *
     * @return string
     */
    protected function getOperation()
    {
        $ast = Parser::parse(new Source($this->query));

        if (! isset($ast->definitions[0])) {
            return 'query';
        }

        $d = $ast->definitions[0];

        return $d->operation ?: 'query';
    }

    /**
     * Determine if selection is a Field.
     *
     * @param  mixed  $selection
     * @return bool
     */
    public function isField($selection)
    {
        return $selection instanceof QueryField;
    }

    /**
     * Find field by operation and name.
     *
     * @param  string $name
     * @param  string $operation
     * @return array
     */
    public function getField($selection, $operation = 'query')
    {
        if (! $this->isField($selection)) {
            return;
        }

        $name = $selection->name->value;

        if ($operation == 'mutation') {
            return $this->schema->getMutationRegistrar()->get($name);
        } elseif ($operation == 'type') {
            return $this->schema->getTypeRegistrar()->get($name);
        }

        return $this->schema->getQueryRegistrar()->get($name);
    }
}
