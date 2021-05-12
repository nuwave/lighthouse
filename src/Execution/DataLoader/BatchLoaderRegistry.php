<?php

namespace Nuwave\Lighthouse\Execution\DataLoader;

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
    public static function instance(string $loaderClass, string $relationName, array $pathToField, array $relationScopes = [], array $fieldArguments, array $constructorArgs = []): object
    {
        // serves as the unique key for the instance
        $instanceKey = self::instanceKey(
            $relationName,
            $pathToField,
            $relationScopes,
            ! empty($fieldArguments) // we want to batch load per field if any field args exist.
        );

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

    /**
     * Generate a unique key for the instance.
     *
     * @param  array<int|string>  $pathToField
     * @param  array<string>  $relationScopes
     */
    protected static function instanceKey(string $relationName, array $pathToField, array $relationScopes = [], bool $batchPerField = false): string
    {
        if ($batchPerField) {
            // Create separate batch loader per field
            // There might be multiple directives on the same field, so we differentiate by relation too
            return self::keyPartFromPath($pathToField)
                .'|relation__'.$relationName;
        }else{

            // Remove field from path array
            $pathWithoutField = $pathToField;
            array_pop($pathWithoutField);

            // Since we aren't batching per field we need to unique it by scopes as well
            return self::keyPartFromPath($pathWithoutField)
                .'|relation__'.$relationName
                .'|scopes__'.implode(',', $relationScopes);
        }
    }

    /**
     * Generate a key part for the instance, using the path in the query.
     *
     * @param  array<int|string>  $path
     */
    protected static function keyPartFromPath(array $path): string
    {
        $significantPathSegments = array_filter(
            $path,
            static function ($segment): bool {
                // Ignore numeric path entries, as those signify a list of fields.
                // Combining the queries for those is the very purpose of the
                // batch loader, so they must not be included.
                return ! is_numeric($segment);
            }
        );

        return implode('.', $significantPathSegments);
    }

}
