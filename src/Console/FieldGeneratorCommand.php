<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Console;

use Illuminate\Console\Concerns\CreatesMatchingTest;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

abstract class FieldGeneratorCommand extends LighthouseGeneratorCommand
{
    use CreatesMatchingTest;

    protected function getStub(): string
    {
        $stub = $this->option('full')
            ? 'field_full'
            : 'field_simple';

        return __DIR__ . "/stubs/{$stub}.stub";
    }

    /** @return array<int, array<int, mixed>> */
    protected function getOptions(): array
    {
        return [
            ['full', 'F', InputOption::VALUE_NONE, 'Include the seldom needed resolver arguments $context and $resolveInfo'],
        ];
    }

    protected function handleTestCreation($path)
    {
        if (! $this->option('test') && ! $this->option('pest')) {
            return false;
        }

        $stubPath = __DIR__.'/stubs/test.stub';
        $stub = $this->files->get($stubPath);

        $name = Str::of($path)->after($this->laravel['path'])->beforeLast('.php');
        $fielName = $name->afterLast('/');

        // The fully qualified class name for the test, replacing slashes with backslashes for namespacing.
        // This assumes that the generated class will be in 'Tests/Feature' namespace.
        $className = $name->prepend('Tests/Feature')->replace('/', '\\')->append('Test');

        $stub = $this->replaceNamespace($stub, $className)->replaceClass($stub, $className);

        // Update the stub content with the correct field and type names
        // - dummyField: the name of the field, in camelCase. e.g getUserName
        // - dummy_field: the name of the field, in snake_case. e.g get_user_name
        // - DummyField: the name of the field, in StudlyCase. e.g GetUserName
        // - dummyType: the name of the type, in lowercase and singular. e.g query
        $stub = Str::of($stub)
            ->replace('dummyField', $fielName->camel())
            ->replace('dummy_field', $fielName->snake())
            ->replace('DummyField', $fielName->studly())
            ->replace('dummyType', Str::of($this->type)->lower()->singular());

        $path = base_path($className->lcfirst()->replace('\\', '/')->append('.php')->toString());

        $this->makeDirectory($path);

        $this->files->put($path, $stub);

        $this->info('Test created successfully.');
    }
}
