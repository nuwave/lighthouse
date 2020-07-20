<?php

namespace Nuwave\Lighthouse\Console;

class MutationCommand extends FieldGeneratorCommand
{
    protected $name = 'lighthouse:mutation';

    protected $description = 'Create a class for a single field on the root Mutation type.';

    protected $type = 'Mutation';

    protected function namespaceConfigKey(): string
    {
        return 'mutations';
    }
}
