<?php

namespace Nuwave\Lighthouse\Console;

class MutationCommand extends LighthouseGeneratorCommand
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'lighthouse:mutation';
    
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a class for a single field on the root Mutation type.';
    
    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Mutation';
    
    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return config('lighthouse.namespaces.mutations');
    }
    
    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub(): string
    {
        return __DIR__ . '/stubs/field.stub';
    }
}
