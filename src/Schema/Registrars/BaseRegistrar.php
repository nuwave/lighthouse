<?php

namespace Nuwave\Lighthouse\Schema\Registrars;

use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Schema\Field;
use Nuwave\Lighthouse\Schema\SchemaBuilder as Schema;
use Nuwave\Lighthouse\Support\Traits\Container\SchemaClassRegistrar;

abstract class BaseRegistrar
{
    use SchemaClassRegistrar;

    /**
     * Collection of registered definitions.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $collection;

    /**
     * Create new instance of registrar.
     * @return void
     */
    public function __construct()
    {
        $this->collection = new Collection;
    }

    /**
     * Add type to registrar.
     *
     * @param  string  $name
     * @param  string  $namespace
     * @return \Nuwave\Lighthouse\Schema\Field
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
     * @param  string  $name
     * @return \Nuwave\Lighthouse\Schema\Field|null
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
     * @param  string  $name
     * @param  string  $namespace
     * @return \Nuwave\Lighthouse\Schema\Field
     */
    protected function createField($name, $namespace)
    {
        $field = new Field($name, $this->getClassName($namespace));

        $field->addMiddleware($this->schema->getMiddlewareStack());

        return $field;
    }

    /**
     * Get instance of schema.
     *
     * @return \Nuwave\Lighthouse\Schema\SchemaBuilder
     */
    public function getSchema()
    {
        return $this->schema ?: app(Schema::class);
    }
}
