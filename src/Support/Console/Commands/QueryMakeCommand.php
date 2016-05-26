<?php

namespace Nuwave\Relay\Support\Console\Commands;

use Illuminate\Console\GeneratorCommand;

class QueryMakeCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'make:relay:query';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a GraphQL/Relay query.';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Query';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return __DIR__.'/stubs/query.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return config('relay.namespaces.queries');
    }
}
