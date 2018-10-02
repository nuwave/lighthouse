<?php

namespace Tests;

use Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider;

class TestSchemaProvider implements SchemaSourceProvider
{
    /**
     * @var string
     */
    protected $schema = '';

    /**
     * TestSchemaProvider constructor.
     *
     * @param string $schema
     */
    public function __construct(string $schema)
    {
        $this->schema = $schema;
    }

    /**
     * @return string
     */
    public function getSchemaString(): string
    {
        return $this->schema;
    }

    /**
     * Set schema root path.
     *
     * @param string $path
     *
     * @return SchemaSourceProvider
     */
    public function setRootPath(string $path)
    {
        return $this;
    }
}
