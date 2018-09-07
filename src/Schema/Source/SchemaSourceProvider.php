<?php

namespace Nuwave\Lighthouse\Schema\Source;

/**
 * Interface SchemaSourceProvider.
 */
interface SchemaSourceProvider
{
    /**
     * Provide the schema definition.
     *
     * @return string
     */
    public function getSchemaString();
}
