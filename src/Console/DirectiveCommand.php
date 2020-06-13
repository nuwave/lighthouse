<?php

namespace Nuwave\Lighthouse\Console;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

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
    protected $description = 'Create a class for a custom schema directive.';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Directive';

    /**
     * The required imports.
     *
     * @var \Illuminate\Support\Collection<string>
     */
    protected $imports;

    /**
     * The implemented interfaces.
     *
     * @var \Illuminate\Support\Collection<string>
     */
    protected $interfaces;

    /**
     * The method stubs.
     *
     * @var \Illuminate\Support\Collection<string>
     */
    protected $methods;

    protected function getNameInput(): string
    {
        return parent::getNameInput().'Directive';
    }

    protected function namespaceConfigKey(): string
    {
        return 'directives';
    }

    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function buildClass($name): string
    {
        $this->imports = new Collection();
        $this->interfaces = new Collection();
        $this->methods = new Collection();

        $stub = parent::buildClass($name);

        if ($this->option('type')) {
            $this->askForInterfaces($stub, [
                'TypeManipulator',
                'TypeMiddleware',
                'TypeResolver',
                'TypeExtensionManipulator',
            ]);
        }

        if ($this->option('field')) {
            $this->askForInterfaces($stub, [
                'FieldResolver',
                'FieldMiddleware',
                'FieldManipulator',
            ]);
        }

        if ($this->option('argument')) {
            // Arg directives always either implement ArgDirective or ArgDirectiveForArray.
            if ($this->confirm('Will your argument directive apply to a list of items?')) {
                $this->implementInterface('ArgDirectiveForArray');
            } else {
                $this->implementInterface('ArgDirective');
            }

            $this->askForInterfaces($stub, [
                'ArgTransformerDirective',
                'ArgBuilderDirective',
                'ArgResolver',
                'ArgManipulator',
            ]);
        }

        $stub = str_replace(
            '{{ imports }}',
            $this->imports
                ->filter()
                ->unique()
                ->implode("\n"),
            $stub
        );

        $stub = str_replace(
            '{{ methods }}',
            $this->methods->implode("\n"),
            $stub
        );

        $stub = str_replace(
            '{{ implements }}',
            $this->interfaces->implode("\n"),
            $stub
        );

        return $stub;
    }

    /**
     * Ask the user if the directive should implement any of the given interfaces.
     *
     * @param  array<string> $interfaces
     */
    protected function askForInterfaces(array $interfaces): void
    {
        foreach ($interfaces as $interface) {
            if ($this->confirm('Should the directive implement the '.$interface.' middleware?')) {
                $this->implementInterface($interface);
            }
        }
    }

    protected function implementInterface(string $interface): void
    {
        $this->interfaces->push($interface);

        $this->imports->push("use Nuwave\\Lighthouse\\Support\\Contracts\\{$interface};");
        if ($imports = $this->interfaceImports($interface)) {
            $imports = explode("\n", $imports);
            $this->imports->push(...$imports);
        }

        if ($methods = $this->interfaceMethods($interface)) {
            $this->methods->push($methods);
        }
    }

    protected function getStub(): string
    {
        return __DIR__.'/stubs/directive.stub';
    }

    protected function interfaceMethods(string $interface): ?string
    {
        return $this->getFileIfExists(
            __DIR__.'/stubs/directives/'.Str::snake($interface).'.stub'
        );
    }

    protected function interfaceImports(string $interface): ?string
    {
        return $this->getFileIfExists(
            __DIR__.'/stubs/directives/'.Str::snake($interface).'_imports.stub'
        );
    }

    protected function getFileIfExists(string $path): ?string
    {
        if (! $this->files->exists($path)) {
            return null;
        }

        return $this->files->get($path);
    }

    /**
     * @return array<array<mixed>>
     */
    protected function getOptions(): array
    {
        return [
            ['type', null, InputOption::VALUE_NONE, 'Create a directive that can be applied to types.'],
            ['field', null, InputOption::VALUE_NONE, 'Create a directive that can be applied to fields.'],
            ['argument', null, InputOption::VALUE_NONE, 'Create a directive that can be applied to arguments.'],
        ];
    }
}
