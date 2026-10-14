<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Console;

use Illuminate\Console\Concerns\CreatesMatchingTest;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Schema\RootType;
use Symfony\Component\Console\Input\InputOption;

abstract class FieldGeneratorCommand extends LighthouseGeneratorCommand
{
    use CreatesMatchingTest;

    protected function getStub(): string
    {
        if (PHP_VERSION_ID >= 80200) {
            return $this->option('full')
                ? __DIR__ . '/stubs/field_full.php82.stub'
                : __DIR__ . '/stubs/field_simple.php82.stub';
        }

        return $this->option('full')
            ? __DIR__ . '/stubs/field_full.stub'
            : __DIR__ . '/stubs/field_simple.stub';
    }

    /** @return array<int, array<int, mixed>> */
    protected function getOptions(): array
    {
        return [
            ['full', 'F', InputOption::VALUE_NONE, 'Include the seldom needed resolver arguments $context and $resolveInfo'],
        ];
    }

    /** @param  string  $path */
    protected function handleTestCreation($path): bool
    {
        if (! $testFramework = $this->testFramework()) {
            return false;
        }

        $stub = $this->files->get(__DIR__ . "/stubs/tests/{$testFramework}.stub");

        // e.g. Mutations/MyMutation
        $operationAndFieldName = Str::of($path)
            ->after($this->laravel->basePath())
            ->after('app')
            ->beforeLast('.php');

        // e.g. Tests\\Feature\\Mutations\\MyMutationTest
        $className = $operationAndFieldName
            ->replace('/', '\\')
            ->prepend('Tests\\Feature')
            ->append('Test');

        $stub = $this->replaceNamespace($stub, $className->toString())
            ->replaceClass($stub, $className->toString());
        $stub = Str::of($stub)
            ->replace('dummyField', $operationAndFieldName->afterLast('/')->toString())
            ->replace('dummyOperationPrefix', $this->operationPrefix())
            ->toString();

        // e.g. tests/Feature/Mutations/MyMutationTest.php
        $classPath = $className->lcfirst()
            ->replace('\\', '/')
            ->append('.php')
            ->toString();

        // e.g. /home/myself/projects/foo/tests/Feature/Mutations/MyMutationTest.php
        $path = base_path($classPath);
        $this->makeDirectory($path);

        $this->files->put($path, $stub);
        $this->info('Test created successfully.');

        return true;
    }

    protected function testFramework(): ?string
    {
        return match (true) {
            $this->option('pest') => 'pest',
            $this->option('test') => 'phpunit',
            default => null,
        };
    }

    protected function operationPrefix(): string
    {
        return match ($this->type) {
            RootType::MUTATION => 'mutation ',
            default => '',
        };
    }
}
