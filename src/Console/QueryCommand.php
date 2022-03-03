<?php

namespace Nuwave\Lighthouse\Console;

use Illuminate\Filesystem\Filesystem;
use Nuwave\Lighthouse\Schema\RootType;

class QueryCommand extends FieldGeneratorCommand
{
    protected $name = 'lighthouse:query';

    protected $description = 'Create a class for a single field on the root Query type.';

    protected $type;

    public function __construct(Filesystem $files)
    {
        $this->type = RootType::Query();

        parent::__construct($files);
    }

    protected function namespaceConfigKey(): string
    {
        return 'queries';
    }
}
