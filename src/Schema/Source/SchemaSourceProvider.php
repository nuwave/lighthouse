<?php

namespace Nuwave\Lighthouse\Schema\Source;

interface SchemaSourceProvider
{
    /**
     * Set schema root path.
     *
     * @deprecated will be removed in v5.
     * @return $this
     */
    public function setRootPath(string $path);

    /**
     * Provide the schema definition.
     */
    public function getSchemaString(): string;
}
