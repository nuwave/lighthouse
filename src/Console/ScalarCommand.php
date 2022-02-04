<?php

namespace Nuwave\Lighthouse\Console;

class ScalarCommand extends LighthouseGeneratorCommand
{
    protected $name = 'lighthouse:scalar';

    protected $description = 'Create a class for a GraphQL scalar type.';

    protected $type = 'Scalar';

    protected function namespaceConfigKey(): string
    {
        return 'scalars';
    }

    protected function getStub(): string
    {
        return __DIR__ . '/stubs/scalar.stub';
    }
}
