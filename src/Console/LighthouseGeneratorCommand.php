<?php

namespace Nuwave\Lighthouse\Console;

use Illuminate\Console\GeneratorCommand;

abstract class LighthouseGeneratorCommand extends GeneratorCommand
{
    /**
     * Get the desired class name from the input.
     *
     * As a typical workflow would be to write the schema first and then copy-paste
     * a field name to generate a class for it, we uppercase it so the user does
     * not run into unnecessary errors. You're welcome.
     *
     * @return string
     */
    protected function getNameInput(): string
    {
        return ucfirst(trim($this->argument('name')));
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        $namespaces = config('lighthouse.namespaces.'.$this->namespaceConfigKey());

        return static::commonNamespace((array) $namespaces);
    }

    /**
     * Get the config key that holds the default namespaces for the class.
     *
     * @return string
     */
    abstract protected function namespaceConfigKey(): string;

    /**
     * Find the common namespace of a list of namespaces.
     *
     * For example, ['App\\Foo\\A', 'App\\Foo\\B'] would return 'App\\Foo'.
     *
     * @param  string[]  $namespaces
     * @return string
     */
    public static function commonNamespace(array $namespaces): string
    {
        if ($namespaces === []) {
            return '';
        }

        if (count($namespaces) === 1) {
            return reset($namespaces);
        }

        // If the strings are sorted, any prefix common to all strings
        // will be common to the sorted first and last strings.
        // All the strings in the middle can be ignored.
        sort($namespaces);

        $first = explode('\\', reset($namespaces));
        $last = explode('\\', end($namespaces));

        $matching = [];
        foreach ($first as $i => $part) {
            // We ran out of elements to compare, so we reached the maximum common length
            if (! isset($last[$i])) {
                break;
            }

            // We found an element that differs
            if ($last[$i] !== $part) {
                break;
            }

            $matching [] = $part;
        }

        return implode('\\', $matching);
    }
}
