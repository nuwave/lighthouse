<?php

namespace Nuwave\Lighthouse\Execution\DataLoader;

use GraphQL\Deferred;

/**
 * @deprecated implement your own batch loader instead
 * @see \Nuwave\Lighthouse\Execution\BatchLoader\BatchLoaderRegistry to resolve instances.
 */
abstract class BatchLoader
{
    /**
     * Active BatchLoader instances.
     *
     * @var array<string, static>
     */
    protected static $instances = [];

    /**
     * Map from keys to metainfo for resolving.
     *
     * @var array<mixed, array<mixed>>
     */
    protected $keys = [];

    /**
     * Map from keys to resolved values.
     *
     * @var array<mixed, mixed>
     */
    protected $results = [];

    /**
     * Check if data has been loaded.
     *
     * @var bool
     */
    protected $hasLoaded = false;

    /**
     * Return an instance of a BatchLoader for a specific field.
     *
     * @param  string  $loaderClass  The class name of the concrete BatchLoader to instantiate
     * @param  array<int|string>  $pathToField  Path to the GraphQL field from the root, is used as a key for BatchLoader instances
     * @param  array<mixed>  $constructorArgs  Those arguments are passed to the constructor of the new BatchLoader instance
     *
     * @throws \Exception
     */
    public static function instance(string $loaderClass, array $pathToField, array $constructorArgs = []): self
    {
        // The path to the field serves as the unique key for the instance
        $instanceName = static::instanceKey($pathToField);

        if (isset(self::$instances[$instanceName])) {
            return self::$instances[$instanceName];
        }

        return self::$instances[$instanceName] = app()->makeWith($loaderClass, $constructorArgs);
    }

    /**
     * Generate a unique key for the instance, using the path in the query.
     *
     * @param  array<int|string>  $path
     */
    public static function instanceKey(array $path): string
    {
        $significantPathSegments = array_filter(
            $path,
            function ($path): bool {
                // Ignore numeric path entries, as those signify a list of fields.
                // Combining the queries for those is the very purpose of the
                // batch loader, so they must not be included.
                return ! is_numeric($path);
            }
        );
        $pathIgnoringLists = implode('.', $significantPathSegments);

        return 'nuwave/lighthouse/batchloader/' . $pathIgnoringLists;
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

    /**
     * Schedule a result to be loaded.
     *
     * @param  array<mixed>  $metaInfo
     */
    public function load(string $key, array $metaInfo = []): Deferred
    {
        $this->keys[$key] = $metaInfo;

        return new Deferred(function () use ($key) {
            if (! $this->hasLoaded) {
                $this->results = $this->resolve();
                $this->hasLoaded = true;
            }

            return $this->results[$key];
        });
    }

    /**
     * Schedule multiple results to be loaded.
     *
     * @param  array<mixed>  $keys
     * @param  array<mixed>  $metaInfo
     *
     * @return array<\GraphQL\Deferred>
     */
    public function loadMany(array $keys, array $metaInfo = []): array
    {
        return array_map(
            function ($key) use ($metaInfo): Deferred {
                return $this->load($key, $metaInfo);
            },
            $keys
        );
    }

    /**
     * Resolve the keys.
     *
     * The result has to be a map from keys to resolved values.
     *
     * @return array<mixed, mixed>
     */
    abstract public function resolve(): array;
}
