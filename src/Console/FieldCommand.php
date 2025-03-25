<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Console;

class FieldCommand extends FieldGeneratorCommand
{
    protected $name = 'lighthouse:field';

    protected $description = 'Create a resolver class for a field on a non-root type.';

    protected $type = 'Resolver';

    protected function namespaceConfigKey(): string
    {
        return 'types';
    }

    protected function getNameInput(): string
    {
        [$type, $field] = $this->nameParts();

        return ucfirst(trim($field));
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        [$type, $field] = $this->nameParts();
        $namespaces = array_map(
            static fn (string $typesNamespace): string => "{$typesNamespace}\\{$type}",
            (array) config("lighthouse.namespaces.{$this->namespaceConfigKey()}"),
        );

        return static::commonNamespace($namespaces);
    }

    /** @return array{string, string} */
    protected function nameParts(): array
    {
        $name = $this->argument('name');
        if (! is_string($name)) {
            throw new \InvalidArgumentException('You must specify the name for the class to generate.');
        }

        $parts = explode('.', $name);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException("You must specify the name as Type.field, got: {$name}.");
        }

        /** @var array{string, string} $parts */

        return $parts;
    }
}
