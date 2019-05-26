<?php

namespace Nuwave\Lighthouse\Console;

class ScalarCommand extends LighthouseGeneratorCommand
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'lighthouse:scalar';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a class for a GraphQL scalar type.';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Scalar';

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return config('lighthouse.namespaces.scalars');
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub(): string
    {
        return __DIR__.'/stubs/scalar.stub';
    }
}
