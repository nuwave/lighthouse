<?php

namespace Nuwave\Lighthouse\Console;

class UnionCommand extends LighthouseGeneratorCommand
{
    protected $name = 'lighthouse:union';

    protected $description = 'Create a class for a GraphQL union type.';

    protected $type = 'Union';

    protected function namespaceConfigKey(): string
    {
        return 'unions';
    }

    protected function getStub(): string
    {
        return __DIR__.'/stubs/typeResolver.stub';
    }
}
