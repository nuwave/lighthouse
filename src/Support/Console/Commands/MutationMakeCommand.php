<?php

namespace Nuwave\Lighthouse\Support\Console\Commands;

use Illuminate\Console\GeneratorCommand;

class MutationMakeCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'lighthouse:mutation';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a GraphQL/Relay mutation.';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Mutation';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        if ($this->option('relay')) {
            return __DIR__.'/stubs/relay_mutation.stub';
        }

        return __DIR__.'/stubs/mutation.stub';
    }

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
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['relay', null, InputOption::VALUE_OPTIONAL, 'Generate a Relay Mutation.'],
        ];
    }
}
