<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Console;

use Nuwave\Lighthouse\Schema\RootType;

class QueryCommand extends FieldGeneratorCommand
{
    protected $name = 'lighthouse:query';

    protected $description = 'Create a resolver class for a single field on the root Query type.';

    protected $type = RootType::QUERY;

    protected function namespaceConfigKey(): string
    {
        return 'queries';
    }
}
