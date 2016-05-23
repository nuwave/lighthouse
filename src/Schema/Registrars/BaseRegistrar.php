<?php

namespace Nuwave\Relay\Schema\Registrars;

use Nuwave\Relay\Schema\Field;
use Nuwave\Relay\Schema\SchemaBuilder as Schema;

abstract class BaseRegistrar
{
    /**
     * Collection of registered definitions.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $collection;

    /**
     * GraphQL schema builder.
     *
     * @var Schema
     */
    protected $schema;

    /**
     * Create new instance of registrar.
     */
    public function __construct()
    {
        $this->collection = collect();
    }

    /**
     * Add type to registrar.
     *
     * @param  string $name
     * @param  string $namespace
     * @return \Nuwave\Relay\Schema\Field
     */
    public function register($name, $namespace)
    {
        $field = $this->createField($name, $namespace);

        $this->collection->put($name, $field);

        return $field;
    }

    /**
     * Resolve instance of requested field.
     *
     * @param  string $name
     * @param  string $namespace
     * @return \Nuwave\Relay\Schema\Field|null
     */
    public function get($name)
    {
        return $this->collection->get($name);
    }

    /**
     * Get copy of collection.
     *
     * @return array
     */
    public function all()
    {
        return $this->collection->all();
    }

    /**
     * Get field and attach necessary middleware.
     *
     * @param  string $name
     * @param  string $namespace
     * @return \Nuwave\Relay\Schema\Field
     */
    protected function createField($name, $namespace)
    {
        $field = new Field($name, $this->getClassName($namespace));

        if ($this->hasMiddlewareStack()) {
            $field->addMiddleware($this->middlewareStack);
        }

        return $field;
    }

    /**
     * Check if middleware stack is empty.
     *
     * @return boolean
     */
    protected function hasMiddlewareStack()
    {
        return !empty($this->middlewareStack);
    }

    /**
     * Get class name.
     *
     * @param  string $namespace
     * @return string
     */
    protected function getClassName($namespace)
    {
        $current = $this->schema->getNamespace();

        return empty(trim($current)) ? $namespace : trim($current, '\\') . '\\' . $namespace;
    }

    /**
     * Set local instance of schema container.
     *
     * @param Schema $schema
     * @return self
     */
    public function setSchema(Schema $schema)
    {
        $this->schema = $schema;

        return $this;
    }

    /**
     * Get instance of schema.
     *
     * @return Schema
     */
    public function getSchema()
    {
        return $this->schema ?: app(Schema::class);
    }
}
