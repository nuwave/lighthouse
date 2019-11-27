<?php

namespace Nuwave\Lighthouse\Console;

class UnionCommand extends LighthouseGeneratorCommand
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
    protected $description = 'Create a class for a GraphQL union type.';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Union';

    protected function namespaceConfigKey(): string
    {
        return 'unions';
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub(): string
    {
        return __DIR__.'/stubs/typeResolver.stub';
    }
}
