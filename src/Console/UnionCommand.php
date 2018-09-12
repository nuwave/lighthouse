<?php

namespace Nuwave\Lighthouse\Console;

use Illuminate\Console\GeneratorCommand;

class UnionCommand extends GeneratorCommand
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'lighthouse:union';
    
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a class for an Union type.';
    
    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Union';
    
    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return config('lighthouse.namespaces.unions');
    }
    
    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub(): string
    {
        return __DIR__ . '/stubs/typeResolver.stub';
    }
}
