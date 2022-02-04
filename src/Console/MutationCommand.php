<?php

namespace Nuwave\Lighthouse\Console;

use Nuwave\Lighthouse\Schema\RootType;

class MutationCommand extends FieldGeneratorCommand
{
    protected $name = 'lighthouse:mutation';

    protected $description = 'Create a class for a single field on the root Mutation type.';

    protected $type = RootType::MUTATION;

    protected function namespaceConfigKey(): string
    {
        return 'mutations';
    }
}
