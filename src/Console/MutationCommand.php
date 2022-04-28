<?php

namespace Nuwave\Lighthouse\Console;

use Illuminate\Filesystem\Filesystem;
use Nuwave\Lighthouse\Schema\RootType;

class MutationCommand extends FieldGeneratorCommand
{
    protected $name = 'lighthouse:mutation';

    protected $description = 'Create a class for a single field on the root Mutation type.';

    protected $type;

    public function __construct(Filesystem $files)
    {
        $this->type = RootType::Mutation();

        parent::__construct($files);
    }

    protected function namespaceConfigKey(): string
    {
        return 'mutations';
    }
}
