<?php

namespace Nuwave\Lighthouse\Execution\DataLoader;

use GraphQL\Deferred;

abstract class BatchLoader
{
    /**
     * Keys to resolve.
     *
     * @var array
     */
    protected $keys = [];

    /**
     * Map of loaded results.
     *
     * [key => resolvedValue]
     *
     * @var array
     */
    private $results = [];

    /**
     * Check if data has been loaded.
     *
     * @var bool
     */
    private $hasLoaded = false;

    /**
     * Return an instance of a BatchLoader for a specific field.
     *
     * @param string $loaderClass The class name of the concrete BatchLoader to instantiate.
     * @param array $pathToField Path to the GraphQL field from the root, is used as a key for BatchLoader instances.
     * @param array $constructorArgs Those arguments are passed to the constructor of the new BatchLoader instance.
     *
     * @throws \Exception
     *
     * @return BatchLoader
     */
    public static function instance(string $loaderClass, array $pathToField, array $constructorArgs = []): self
    {
        // The path to the field serves as the unique key for the instance
        $instanceName = static::instanceKey($pathToField);
        
        // Only register a new instance if it is not already bound
        $instance = app()->bound($instanceName)
            ? resolve($instanceName)
            : app()->instance(
                $instanceName,
                app()->makeWith($loaderClass, $constructorArgs)
            );
        
        if (!$instance instanceof self) {
            throw new \Exception("The given class '$loaderClass' must resolve to an instance of Nuwave\Lighthouse\Execution\DataLoader\BatchLoader");
        }
        
        return $instance;
    }

    /**
     * Generate a unique key for the instance, using the path in the query.
     *
     * @param array $path
     *
     * @return string
     */
    public static function instanceKey(array $path): string
    {
        return collect($path)
            ->filter(function ($path) {
                // Ignore numeric path entries, as those signify an array of fields
                // Those are the very purpose for this batch loader, so they must not be included.
                return !is_numeric($path);
            })
            ->implode('_');
    }

    /**
     * Load object by key.
     *
     * @param mixed $key
     * @param array $metaInfo
     *
     * @return Deferred
     */
    public function load($key, array $metaInfo = []): Deferred
    {
        $this->keys[$key] = $metaInfo;

        return new Deferred(function () use ($key) {
            if (!$this->hasLoaded) {
                $this->results = $this->resolve();
                $this->hasLoaded = true;
            }

            return $this->results[$key];
        });
    }

    /**
     * Resolve the keys.
     *
     * The result has to be a map: [key => result]
     */
    abstract public function resolve(): array;
}
