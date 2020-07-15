<?php

namespace Nuwave\Lighthouse\Console;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirectiveForArray;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;
use Nuwave\Lighthouse\Support\Contracts\ArgTransformerDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\TypeExtensionManipulator;
use Nuwave\Lighthouse\Support\Contracts\TypeManipulator;
use Nuwave\Lighthouse\Support\Contracts\TypeMiddleware;
use Nuwave\Lighthouse\Support\Contracts\TypeResolver;
use Symfony\Component\Console\Input\InputOption;

class DirectiveCommand extends LighthouseGeneratorCommand
{
    const ARGUMENT_INTERFACES = [
        ArgTransformerDirective::class,
        ArgBuilderDirective::class,
        ArgResolver::class,
        ArgManipulator::class,
    ];

    const FIELD_INTERFACES = [
        FieldResolver::class,
        FieldMiddleware::class,
        FieldManipulator::class,
    ];

    const TYPE_INTERFACES = [
        TypeManipulator::class,
        TypeMiddleware::class,
        TypeResolver::class,
        TypeExtensionManipulator::class,
    ];

    protected $name = 'lighthouse:directive';

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
    protected $implements;

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
     * @param  string  $name
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function buildClass($name): string
    {
        $this->imports = new Collection();
        $this->implements = new Collection();
        $this->methods = new Collection();

        $stub = parent::buildClass($name);

        if ($this->option('type')) {
            $this->askForInterfaces(self::TYPE_INTERFACES);
        }

        if ($this->option('field')) {
            $this->askForInterfaces(self::FIELD_INTERFACES);
        }

        if ($this->option('argument')) {
            // Arg directives always either implement ArgDirective or ArgDirectiveForArray.
            if ($this->confirm('Will your argument directive apply to a list of items?')) {
                $this->implementInterface(ArgDirectiveForArray::class);
            } else {
                $this->implementInterface(ArgDirective::class);
            }

            $this->askForInterfaces(self::ARGUMENT_INTERFACES);
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
            $this->implements->implode(', '),
            $stub
        );

        return $stub;
    }

    /**
     * Ask the user if the directive should implement any of the given interfaces.
     *
     * @param  array<class-string> $interfaces
     */
    protected function askForInterfaces(array $interfaces): void
    {
        foreach ($interfaces as $interface) {
            if ($this->confirm("Should the directive implement the {$this->shortName($interface)} middleware?")) {
                $this->implementInterface($interface);
            }
        }
    }

    /**
     * @param  class-string  $interface
     */
    protected function shortName(string $interface): string
    {
        return Str::afterLast($interface, '\\');
    }

    /**
     * @param  class-string  $interface
     */
    protected function implementInterface(string $interface): void
    {
        $shortName = $this->shortName($interface);
        $this->implements->push($shortName);

        $this->imports->push("use {$interface};");
        if ($imports = $this->interfaceImports($shortName)) {
            $imports = explode("\n", $imports);
            $this->imports->push(...$imports);
        }

        if ($methods = $this->interfaceMethods($shortName)) {
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
            __DIR__.'/stubs/directives/'.Str::snake($interface).'_methods.stub'
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
