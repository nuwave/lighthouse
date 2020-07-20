<?php

namespace Nuwave\Lighthouse\Console;

class QueryCommand extends FieldGeneratorCommand
{
    protected $name = 'lighthouse:query';

    protected $description = 'Create a class for a single field on the root Query type.';

    protected $type = 'Query';

    protected function namespaceConfigKey(): string
    {
        return 'queries';
    }
}
