<?php

namespace Nuwave\Lighthouse\Schema\Source;

interface SchemaSourceProvider
{
    /**
     * Set schema root path.
     *
     * @deprecated will be removed in v5.
     * @param  string  $path
     * @return \Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider
     */
    public function setRootPath(string $path);

    /**
     * Provide the schema definition.
     *
     * @return string
     */
    public function getSchemaString(): string;
}
