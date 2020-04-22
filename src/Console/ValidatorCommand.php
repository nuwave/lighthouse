<?php


namespace Nuwave\Lighthouse\Console;


/**
 * Class ValidatorCommand
 */
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

    protected function getStub()
    {
        return __DIR__.'/stubs/validator.stub';
    }

    protected function namespaceConfigKey(): string
    {
        return 'validators';
    }
}
