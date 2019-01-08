<?php

namespace Nuwave\Lighthouse\Schema\Source;

/**
 * Interface SchemaSourceProvider.
 */
interface SchemaSourceProvider
{
    /**
     * Set schema root path.
     *
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
