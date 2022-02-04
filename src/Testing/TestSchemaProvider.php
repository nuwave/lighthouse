<?php

namespace Nuwave\Lighthouse\Testing;

use Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider;

class TestSchemaProvider implements SchemaSourceProvider
{
    /**
     * @var string
     */
    protected $schema = '';

    /**
     * @param  string  $schema  May be changed after instantiation, so it is passed as a reference
     */
    public function __construct(string &$schema)
    {
        $this->schema = &$schema;
    }

    public function getSchemaString(): string
    {
        return $this->schema;
    }
}
