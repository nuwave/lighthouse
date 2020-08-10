<?php

namespace Nuwave\Lighthouse\Execution\DataLoader;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;

class RelationFetcher
{

    /**
     * Extract the parent from the keys of the BatchLoader.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<mixed> $keys
     */
    protected static function extractParents(Collection $keys): SupportCollection
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
     * Get the parents from the keys that are present on the BatchLoader.
     *
     * @param array<mixed, array<mixed>> $keys
     * @return \Illuminate\Database\Eloquent\Collection<mixed>
     */
    public static function getParentModels(array $keys): Collection
    {
        return static::groupModelsByClassKey($keys)
            ->mapWithKeys(
                /**
                 * @param  \Illuminate\Database\Eloquent\Collection<Model>  $keys
                 */
                function (Collection $keys) {;
                    return new Collection(static::extractParents($keys));
                }
            );
    }

    /**
     * Get the parents from the keys that are present on the BatchLoader and
     * and load the count of the given relation.
     *
     * @param array<mixed, array<mixed>> $keys
     * @param array<string, \Closure> $relation
     */
    public static function getCountedParentModels(array $keys, array $relation): Collection
    {
        return static::groupModelsByClassKey($keys)
            ->mapWithKeys(
                /**
                 * @param  \Illuminate\Database\Eloquent\Collection<array>  $keys
                 */
                function (Collection $keys) use ($relation) {
                    return (new Collection(static::extractParents($keys)))
                        ->tap(
                            /**
                             * @param Illuminate\Database\Eloquent\Collection<mixed> $parents
                             */
                            function (Collection $parents) use ($relation): void {
                                $parents->loadCount($relation);
                            }
                        );
                }
            );
    }

    /**
     * Get the parents from the keys that are present on the BatchLoader and
     * and load the given relation.
     *
     * @param array<mixed, array<mixed>> $keys
     * @param array<string, \Closure> $relation
     */
    public static function getLoadedParentModels(array $keys, array $relation): Collection
    {
        return static::groupModelsByClassKey($keys)
            ->mapWithKeys(
                /**
                 * @param  \Illuminate\Database\Eloquent\Collection<array>  $keys
                 */
                function (Collection $keys) use ($relation) {
                    return (new Collection(static::extractParents($keys)))
                        ->tap(
                            /**
                             * @param Illuminate\Database\Eloquent\Collection<mixed> $parents
                             */
                            function (Collection $parents) use ($relation): void {
                                $parents->load($relation);
                            }
                        );
                }
            );
    }

    /**
     * Group the models by their fully qualified class name to prevent key
     * collisions between different types of models.
     *
     * @param array<mixed, array<mixed>> $keys
     */
    protected static function groupModelsByClassKey(array $keys): Collection
    {
        return (new Collection($keys))
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
