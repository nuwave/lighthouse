<?php

namespace Nuwave\Relay;

use GraphQL\Schema;
use GraphQL\Type\Definition\ObjectType;
use Illuminate\Support\Collection;
use Nuwave\Relay\Support\Definition\GraphQLQuery;
use Nuwave\Relay\Support\Traits\Container\TypeRegistrar;
use Nuwave\Relay\Support\Traits\Container\QueryExecutor;
use Nuwave\Relay\Support\Traits\Container\QueryRegistrar;
use Nuwave\Relay\Support\Traits\Container\MutationRegistrar;
use Nuwave\Relay\Schema\SchemaBuilder;

class GraphQL
{
    use MutationRegistrar,
        QueryExecutor,
        QueryRegistrar,
        TypeRegistrar;

    /**
     * Instance of application.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    /**
     * Instance of schema builder.
     *
     * @var SchemaBuilder
     */
    protected $schema;

    /**
     * Create new instance of graphql container.
     *
     * @param \Illuminate\Contracts\Foundation\Application $app
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * Generate GraphQL Schema.
     *
     * @return \GraphQL\Schema
     */
    public function buildSchema()
    {
        $queryType = $this->generateSchemaType($this->getQueries(), 'Query');
        $mutationType = $this->generateSchemaType($this->getMutations(), 'Mutation');

        return new Schema($queryType, $mutationType);
    }

    /**
     * Generate type from collection of fields.
     *
     * @param  Collection $fields
     * @param  array     $options
     * @return \GraphQL\Type\Definition\ObjectType
     */
    protected function generateSchemaType(Collection $fields, $name)
    {
        $typeFields = $fields->map(function ($field, $key) {
            if (is_string($field)) {
                return array_merge(['name' => $key], app($field)->toArray());
            } elseif ($field instanceof GraphQLQuery) {
                return array_merge(['name' => $key], $field->toArray());
            } elseif ($field instanceof ObjectType) {
                return $field->config;
            }

            return $field;
        });

        return new ObjectType([
            'name' => $name,
            'fields' => $typeFields->toArray(),
        ]);
    }

    /**
     * Extract instance from type registrar.
     *
     * @param  string $name
     * @param  boolean $fresh
     * @return ObjectType
     */
    public function type($name, $fresh = false)
    {
        return $this->schema()->typeInstance($name, $fresh);
    }

    /**
     * Extract instance from edge registrar.
     *
     * @param  string $name
     * @param  boolean $fresh
     * @return ObjectType
     */
    public function edge($name, $fresh = false)
    {
        return $this->schema()->edgeInstance($name, $fresh);
    }

    /**
     * Set local instance of schema.
     *
     * @param SchemaBuilder $schema
     */
    public function setSchema(SchemaBuilder $schema)
    {
        $this->schema = $schema;
    }

    /**
     * Get instance of schema builder.
     *
     * @return SchemaBuilder
     */
    public function schema()
    {
        if (!$this->schema) {
            $this->schema = app(SchemaBuilder::class);
        }

        return $this->schema;
    }
}
