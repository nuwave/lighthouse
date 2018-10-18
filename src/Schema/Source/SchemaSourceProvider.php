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
     * @param string $path
     *
     * @return SchemaSourceProvider
     */
    public function setRootPath(string $path);

    /**
     * Get schema root path.
     *
     * @return string
     */
    public function getRootPath();

    /**
     * Provide the schema definition.
     *
     * @return string
     */
    public function getSchemaString();
}
