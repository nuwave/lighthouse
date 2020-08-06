<?php

namespace Nuwave\Lighthouse\Schema\Source;

interface SchemaSourceProvider
{
    /**
     * Provide the schema definition.
     */
    public function getSchemaString(): string;
}
