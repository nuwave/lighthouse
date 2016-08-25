<?php

namespace Nuwave\Lighthouse\Support\Console\Commands;

use ReflectionClass;
use Nuwave\Lighthouse\Support\Definition\EloquentType;
use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;

class TypeMakeCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'lighthouse:type';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a GraphQL/Relay type.';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Type';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        if ($this->option('relay')) {
            return __DIR__.'/stubs/relay_type.stub';
        }

        return __DIR__.'/stubs/type.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return config('lighthouse.namespaces.types');
    }

    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     * @return string
     */
    protected function buildClass($name)
    {
        if ($model = $this->option('model')) {
            $stub = $this->getEloquentStub($model);
        } else {
            $stub = $this->files->get($this->getStub());
        }

        return $this->replaceNamespace($stub, $name)->replaceClass($stub, $name);
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['relay', null, InputOption::VALUE_NONE, 'Generate a Relay GraphQL type.'],
            ['model', null, InputOption::VALUE_OPTIONAL, 'Generate a Eloquent GraphQL type.'],
        ];
    }

    /**
     * Generate stub from eloquent type.
     *
     * @param  string $model
     * @return string
     */
    protected function getEloquentStub($model)
    {
        $shortName = $model;
        $rootNamespace = $this->laravel->getNamespace();

        if (starts_with($model, $rootNamespace)) {
            $shortName = (new ReflectionClass($model))->getShortName();
        } else {
            $model = config('lighthouse.model_path') . "\\" . $model;
        }

        $relay = $this->option('relay');
        $fields = $this->getTypeFields($model);

        return "<?php\n\n" . view('lighthouse::eloquent', compact('model', 'shortName', 'fields', 'relay'))->render();
    }

    /**
     * Generate fields for type.
     *
     * @param  string $class
     * @return array
     */
    protected function getTypeFields($class)
    {
        $model = app($class);

        return (new EloquentType($model))->rawFields();
    }
}
