<?php

namespace Nuwave\Lighthouse\Schema\Source;

/**
 * Interface SchemaSourceProvider
 * @package Nuwave\Lighthouse\Schema\Source
 *
 * This interface can be implemented to have different methods of providing
 * a schema string. Since the schema is then parsed into an AST and cached,
 * this does not necessarily have to be very performant, e.g. it may be
 * acceptable to download the schema or fetch it from an API.
 */
interface SchemaSourceProvider
{
    /**
     * Provide the schema definition.
     *
     * @return string
     */
    public function getSchemaString(): string;
}
