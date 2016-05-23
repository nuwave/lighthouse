<?php

namespace Nuwave\Relay;

use Closure;
use GraphQL\Schema;
use GraphQL\Type\Definition\ObjectType;
use Illuminate\Support\Collection;
use Nuwave\Relay\Support\Traits\Container\QueryExecutor;
use Nuwave\Relay\Support\Traits\Container\MutationRegistrar;
use Nuwave\Relay\Schema\Field;
use Nuwave\Relay\Schema\SchemaBuilder;

class GraphQL
{
    use QueryExecutor;

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
        $queryFields = $this->queries()->merge($this->connections()->toArray());
        $mutationFields = $this->mutations();

        $queryType = $this->generateSchemaType($queryFields, 'Query');
        $mutationType = $this->generateSchemaType($mutationFields, 'Mutation');

        return new Schema($queryType, $mutationType);
    }

    /**
     * Generate type from collection of fields.
     *
     * @param  Collection $fields
     * @param  array     $options
     * @return \GraphQL\Type\Definition\ObjectType|null
     */
    protected function generateSchemaType(Collection $fields, $name)
    {
        $typeFields = $fields->map(function ($field, $key) {
            if ($field instanceof Field) {
                return app($field->namespace)->toArray();
            }

            return $field;
        });

        if (! $typeFields->count()) {
            return null;
        }

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
     * Extract instance from connection registrar.
     *
     * @param  string $name
     * @param  Closure|null $resolve
     * @param  boolean $fresh
     * @return ObjectType
     */
    public function connection($name, Closure $resolve = null, $fresh = false)
    {
        $connection = $this->schema()->connectionInstance($name, $resolve, $fresh);

        if (! $this->connections()->has($name)) {
            $this->schema()->connection($name, $connection);
        }

        return $connection;
    }

    /**
     * Extract instance from edge registrar.
     *
     * @param  string $name
     * @param  ObjectType $type
     * @param  boolean $fresh
     * @return ObjectType
     */
    public function edge($name, ObjectType $type = null, $fresh = false)
    {
        return $this->schema()->edgeInstance($name, $type, $fresh);
    }

    /**
     * Get collection of registered types.
     *
     * @return \Illuminate\Support\Collection
     */
    public function types()
    {
        return collect($this->schema()->getTypeRegistrar()->all());
    }

    /**
     * Get collection of registered queries.
     *
     * @return \Illuminate\Support\Collection
     */
    public function queries()
    {
        return collect($this->schema()->getQueryRegistrar()->all());
    }

    /**
     * Get collection of registered mutations.
     *
     * @return \Illuminate\Support\Collection
     */
    public function mutations()
    {
        return collect($this->schema()->getMutationRegistrar()->all());
    }

    /**
     * Get collection of registered connections.
     *
     * @return \Illuminate\Support\Collection
     */
    public function connections()
    {
        return collect($this->schema()->getConnectionRegistrar()->all());
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
