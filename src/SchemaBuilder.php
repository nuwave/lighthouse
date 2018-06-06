<?php

namespace Nuwave\Lighthouse;

use Nuwave\Lighthouse\Schema\Schema;

interface SchemaBuilder
{
    public function buildFromTypeLanguage(string $schema): Schema;
}
