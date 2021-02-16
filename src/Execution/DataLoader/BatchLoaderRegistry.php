<?php

namespace Nuwave\Lighthouse\Execution\DataLoader;

use Nuwave\Lighthouse\Execution\Utils\FieldPath;

abstract class BatchLoaderRegistry
{
    /**
     * Active BatchLoader instances.
     *
     * @var array<string, object>
     */
    protected static $instances = [];

    /**
     * Return an instance of a BatchLoader for a specific field.
     *
     * @param  class-string  $loaderClass  The class name of the concrete BatchLoader to instantiate
     * @param  array<int|string>  $pathToField  Path to the GraphQL field from the root, is used as a key for BatchLoader instances
     * @param  array<mixed>  $constructorArgs  Those arguments are passed to the constructor of the new BatchLoader instance
     * @return object An instance of the passed in class
     *
     * @throws \Exception
     */
    public static function instance(string $loaderClass, array $pathToField, array $constructorArgs = []): object
    {
        // The path to the field serves as the unique key for the instance
        $instanceKey = FieldPath::withoutLists($pathToField);

        if (isset(self::$instances[$instanceKey])) {
            return self::$instances[$instanceKey];
        }

        return self::$instances[$instanceKey] = app()->makeWith($loaderClass, $constructorArgs);
    }

    /**
     * Remove all stored BatchLoaders.
     *
     * This is called after Lighthouse has resolved a query, so multiple
     * queries can be handled in a single request/session.
     */
    public static function forgetInstances(): void
    {
        self::$instances = [];
    }
}
