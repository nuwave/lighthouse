<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Nuwave\Lighthouse\Schema\Schema;
use Nuwave\Lighthouse\Types\Type;

interface SchemaBuilder
{
    /**
     * Generates the schema from type system language.
     *
     * It should generate the schema with extension types and run our
     * manipulators on the types. For simplifying use the abstract
     * schema builder.
     *
     * @see \Nuwave\Lighthouse\Schema\SchemaBuilder
     * @param string $typeLanguage
     * @return Schema
     */
    public function buildFromTypeLanguage(string $typeLanguage): Schema;
}
