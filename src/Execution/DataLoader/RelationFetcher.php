<?php

namespace Nuwave\Lighthouse\Execution\DataLoader;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;

class RelationFetcher
{
    /**
     * Get the parents from the keys that are present on the BatchLoader.
     *
     * @param array<mixed, array<mixed>> $keys
     * @return \Illuminate\Database\Eloquent\Collection<mixed>
     */
    public static function parentModels(array $keys): EloquentCollection
    {
        return static::groupModelsByClassKey($keys)
            ->mapWithKeys(
                /**
                 * @param  \Illuminate\Database\Eloquent\Collection<Model>  $keys
                 */
                function (EloquentCollection $keys) {
                    return new EloquentCollection(static::extractParents($keys));
                }
            );
    }

    /**
     * Extract the parents from the keys and load the count of the given relation.
     *
     * @param array<mixed, array<mixed>> $keys
     * @param array<string, \Closure> $relation
     */
    public static function countedParentModels(array $keys, array $relation): EloquentCollection
    {
        return static::groupModelsByClassKey($keys)
            ->mapWithKeys(
                /**
                 * @param  \Illuminate\Database\Eloquent\Collection<array>  $keys
                 */
                function (EloquentCollection $keys) use ($relation) {
                    return (new EloquentCollection(static::extractParents($keys)))
                        ->tap(
                            /**
                             * @param \Illuminate\Database\Eloquent\Collection<mixed> $parents
                             */
                            function (EloquentCollection $parents) use ($relation): void {
                                $parents->loadCount($relation);
                            }
                        );
                }
            );
    }

    /**
     * Extract the parents from the keys and load the given relation.
     *
     * @param array<mixed, array<mixed>> $keys
     * @param array<string, \Closure> $relation
     */
    public static function loadedParentModels(array $keys, array $relation): EloquentCollection
    {
        return static::groupModelsByClassKey($keys)
            ->mapWithKeys(
                /**
                 * @param  \Illuminate\Database\Eloquent\Collection<array>  $keys
                 */
                function (EloquentCollection $keys) use ($relation) {
                    return (new EloquentCollection(static::extractParents($keys)))
                        ->tap(
                            /**
                             * @param \Illuminate\Database\Eloquent\Collection<mixed> $parents
                             */
                            function (EloquentCollection $parents) use ($relation): void {
                                $parents->load($relation);
                            }
                        );
                }
            );
    }

    /**
     * Extract the parent from the keys of the BatchLoader.
     *
     * @param  \Illuminate\Support\Collection<mixed> $keys
     */
    protected static function extractParents(EloquentCollection $keys): SupportCollection
    {
        return $keys->map(
        /**
         * @param  array<string, mixed>  $meta
         */
            function (array $meta) {
                return $meta['parent'];
            }
        );
    }

    /**
     * Group the models by their fully qualified class name to prevent key collisions between different types of models.
     *
     * @param array<mixed, array<mixed>> $keys
     */
    protected static function groupModelsByClassKey(array $keys): EloquentCollection
    {
        return (new EloquentCollection($keys))
            ->groupBy(
                /**
                 * @param  array<string, mixed>  $key
                 * @return class-string<\Illuminate\Database\Eloquent\Model>
                 */
                static function (array $key): string {
                    return get_class($key['parent']);
                },
                true
            );
    }
}
