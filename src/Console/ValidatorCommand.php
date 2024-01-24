<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Console;

class ValidatorCommand extends LighthouseGeneratorCommand
{
    protected $name = 'lighthouse:validator';

    protected $description = 'Create a validator class';

    protected $type = 'Validator';

    protected function namespaceConfigKey(): string
    {
        return 'validators';
    }

    protected function getStub(): string
    {
        return __DIR__ . '/stubs/validator.stub';
    }
}
