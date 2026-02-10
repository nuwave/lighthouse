<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution\BatchLoader;

use Nuwave\Lighthouse\Execution\Utils\FieldPath;

abstract class BatchLoaderRegistry
{
    /**
     * Active BatchLoader instances.
     *
     * @var array<string, object>
     */
    protected static array $instances = [];

    /**
     * Return an instance of a BatchLoader for a specific field.
     *
     * @template TBatchLoader of object
     *
     * @param  array<int|string>  $pathToField  Path to the GraphQL field from the root, is used as a key for BatchLoader instances
     * @param  callable(): TBatchLoader  $makeInstance  Function to instantiate the instance once
     *
     * @return TBatchLoader The result of calling makeInstance
     */
    public static function instance(array $pathToField, callable $makeInstance): object
    {
        // The path to the field serves as the unique key for the instance
        $instanceKey = FieldPath::withoutLists($pathToField);

        // @phpstan-ignore-next-line Method Nuwave\Lighthouse\Execution\BatchLoader\BatchLoaderRegistry::instance() should return TBatchLoader of object but returns object.
        return self::$instances[$instanceKey] ??= $makeInstance();
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
