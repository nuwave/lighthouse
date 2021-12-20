<?php

namespace Nuwave\Lighthouse\Console;

class InterfaceCommand extends LighthouseGeneratorCommand
{
    protected $name = 'lighthouse:interface';

    protected $description = 'Create a class for a GraphQL interface type.';

    protected $type = 'Interface';

    protected function namespaceConfigKey(): string
    {
        return 'interfaces';
    }

    protected function getStub(): string
    {
        return __DIR__ . '/stubs/typeResolver.stub';
    }
}
