<?php

namespace Nuwave\Lighthouse\Console;

class DirectiveCommand extends LighthouseGeneratorCommand
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'lighthouse:directive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a class for a directive.';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Directive';

    protected function getNameInput(): string
    {
        return ucfirst($this->argument('name')).'Directive';
    }

    protected function namespaceConfigKey(): string
    {
        return 'directives';
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub(): string
    {
        return __DIR__.'/stubs/directive.stub';
    }
}
