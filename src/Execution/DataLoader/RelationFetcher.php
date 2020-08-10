<?php

namespace Nuwave\Lighthouse\Execution\DataLoader;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

class RelationFetcher
{
    /**
     * Get the parents from the keys that are present on the BatchLoader.
     *
     * @param array<int, array<string, mixed>> $keys
     * @return \Illuminate\Database\Eloquent\Collection<\Illuminate\Database\Eloquent\Model>
     */
    public static function extractParentModels(array $keys): EloquentCollection
    {
        return (new EloquentCollection($keys))
            ->map(
                /**
                 * @param  array<string, mixed>  $meta
                 */
                static function (array $meta): Model {
                    return $meta['parent'];
                }
            );
    }

    /**
     * Extract the parents from the keys and load the count of the given relation.
     *
     * @param  array<int, array<string, mixed>>  $keys
     * @param  array<string, \Closure>  $relation
     * @return  \Illuminate\Database\Eloquent\Collection<\Illuminate\Database\Eloquent\Model>
     */
    public static function countedParentModels(array $keys, array $relation): EloquentCollection
    {
        return static::groupModelsByClassKey($keys)
            ->flatMap(
                /**
                 * @param  \Illuminate\Database\Eloquent\Collection<\Illuminate\Database\Eloquent\Model>  $models
                 * @return  \Illuminate\Database\Eloquent\Collection<\Illuminate\Database\Eloquent\Model>
                 */
                static function (EloquentCollection $models) use ($relation): EloquentCollection {
                    $models->loadCount($relation);

                    return $models;
                }
            );
    }

    /**
     * Extract the parents from the keys and load the given relation.
     *
     * @param  array<int, array<string, mixed>>  $keys
     * @param array<string, \Closure> $relation
     * @return \Illuminate\Database\Eloquent\Collection<\Illuminate\Database\Eloquent\Model>
     */
    public static function loadedParentModels(array $keys, array $relation): EloquentCollection
    {
        return static::groupModelsByClassKey($keys)
            ->flatMap(
                /**
                 * @param  \Illuminate\Database\Eloquent\Collection<\Illuminate\Database\Eloquent\Model>  $models
                 * @return  \Illuminate\Database\Eloquent\Collection<\Illuminate\Database\Eloquent\Model>
                 */
                static function (EloquentCollection $models) use ($relation): EloquentCollection {
                    $models->load($relation);

                    return $models;
                }
            );
    }

    /**
     * Group the models by their fully qualified class name.
     *
     * This prevents key collisions between different types of models.
     *
     * @param array<int, array<string, mixed>> $keys
     * @return \Illuminate\Database\Eloquent\Collection<\Illuminate\Database\Eloquent\Collection<\Illuminate\Database\Eloquent\Model>>
     */
    protected static function groupModelsByClassKey(array $keys): EloquentCollection
    {
        return self::extractParentModels($keys)
            ->groupBy(
                /**
                 * @return class-string<\Illuminate\Database\Eloquent\Model>
                 */
                static function (Model $model): string {
                    return get_class($model);
                },
                true
            );
    }
}
