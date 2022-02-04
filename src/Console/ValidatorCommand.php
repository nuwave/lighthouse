<?php

namespace Nuwave\Lighthouse\Console;

class ValidatorCommand extends LighthouseGeneratorCommand
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'lighthouse:validator';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a validator class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Validator';

    protected function namespaceConfigKey(): string
    {
        return 'validators';
    }

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return __DIR__ . '/stubs/validator.stub';
    }
}
