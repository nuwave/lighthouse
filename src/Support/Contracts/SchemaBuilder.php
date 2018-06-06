<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Nuwave\Lighthouse\Schema\Schema;

interface SchemaBuilder
{
    public function buildFromTypeLanguage(string $schema): Schema;
}
