<?php

namespace Nuwave\Lighthouse\Support\Traits\Container;

use Nuwave\Lighthouse\Schema\SchemaBuilder;

trait SchemaClassRegistrar
{
    /**
     * Set local instance of schema container.
     *
     * @param SchemaBuilder $schema
     * @return self
     */
    public function setSchema(SchemaBuilder $schema)
    {
        $this->schema = $schema;

        return $this;
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
}
