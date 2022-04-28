<?php

namespace Nuwave\Lighthouse\Console;

use Illuminate\Console\Command;
use Nuwave\Lighthouse\Schema\Validator as SchemaValidator;

class ValidateSchemaCommand extends Command
{
    protected $name = 'lighthouse:validate-schema';

    protected $description = 'Validate the GraphQL schema definition.';

    public function handle(SchemaValidator $schemaValidator): void
    {
        $schemaValidator->validate();

        $this->info('The defined schema is valid.');
    }
}
