<?php

namespace Nuwave\Lighthouse\Console;

use Illuminate\Console\GeneratorCommand;
use InvalidArgumentException;

abstract class LighthouseGeneratorCommand extends GeneratorCommand
{
    /**
     * Get the desired class name from the input.
     *
     * As a typical workflow would be to write the schema first and then copy-paste
     * a field name to generate a class for it, we uppercase it so the user does
     * not run into unnecessary errors. You're welcome.
     */
    protected function getNameInput(): string
    {
        $name = $this->argument('name');
        if (! is_string($name)) {
            throw new InvalidArgumentException('You must the name for the class to generate.');
        }

        return ucfirst(trim($name));
    }

    /**
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        $namespaces = config('lighthouse.namespaces.'.$this->namespaceConfigKey());

        return static::commonNamespace((array) $namespaces);
    }

    /**
     * Get the config key that holds the default namespaces for the class.
     */
    abstract protected function namespaceConfigKey(): string;

    /**
     * Find the common namespace of a list of namespaces.
     *
     * For example, ['App\\Foo\\A', 'App\\Foo\\B'] would return 'App\\Foo'.
     *
     * @param  string[]  $namespaces
     */
    public static function commonNamespace(array $namespaces): string
    {
        if ($namespaces === []) {
            throw new InvalidArgumentException(
                'A default namespace is required for code generation.'
            );
        }

        if (count($namespaces) === 1) {
            return reset($namespaces);
        }

        // Save the first namespac
        $preferredNamespaceFallback = reset($namespaces);

        // If the strings are sorted, any prefix common to all strings
        // will be common to the sorted first and last strings.
        // All the strings in the middle can be ignored.
        sort($namespaces);

        $firstParts = explode('\\', reset($namespaces));
        $lastParts = explode('\\', end($namespaces));

        $matching = [];
        foreach ($firstParts as $i => $part) {
            // We ran out of elements to compare, so we reached the maximum common length
            if (! isset($lastParts[$i])) {
                break;
            }

            // We found an element that differs
            if ($lastParts[$i] !== $part) {
                break;
            }

            $matching [] = $part;
        }

        // We could not determine a common part of the configured namespaces,
        // so we just assume the user will prefer the first one in the list.
        if ($matching === []) {
            return $preferredNamespaceFallback;
        }

        return implode('\\', $matching);
    }
}
