<?php

namespace Nuwave\Lighthouse\Execution\DataLoader;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

class RelationFetcher
{
    /**
     * Get the parents from the keys that are present on the BatchLoader.
     *
     * @param  array<string, array<string, mixed>>  $keys
     * @return \Illuminate\Database\Eloquent\Collection<\Illuminate\Database\Eloquent\Model>
     */
    public static function extractParentModels(array $keys): EloquentCollection
    {
        $models = [];

        foreach ($keys as $key => $meta) {
            $models[$key] = $meta['parent'];
        }

        return new EloquentCollection($models);
    }

    /**
     * Extract the parents from the keys and load the given relation.
     *
     * @param  array<string, array<string, mixed>>  $keys
     * @param  array<string, \Closure>  $relation
     * @return \Illuminate\Database\Eloquent\Collection<\Illuminate\Database\Eloquent\Model>
     */
    public static function loadedParentModels(array $keys, array $relation): EloquentCollection
    {
        $allModels = [];

        foreach (static::groupModelsByClassKey($keys) as $modelsOfSameClass) {
            $modelsOfSameClass->load($relation);

            foreach ($modelsOfSameClass as $key => $model) {
                $allModels[$key] = $model;
            }
        }

        return new EloquentCollection($allModels);
    }

    /**
     * Extract the parents from the keys and load the count of the given relation.
     *
     * @param  array<string, array<string, mixed>>  $keys
     * @param  array<string, \Closure>  $relation
     * @return \Illuminate\Database\Eloquent\Collection<\Illuminate\Database\Eloquent\Model>
     */
    public static function countedParentModels(array $keys, array $relation): EloquentCollection
    {
        $allModels = [];

        foreach (static::groupModelsByClassKey($keys) as $modelsOfSameClass) {
            $modelsOfSameClass->loadCount($relation);

            foreach ($modelsOfSameClass as $key => $model) {
                $allModels[$key] = $model;
            }
        }

        return new EloquentCollection($allModels);
    }

    /**
     * Group the models by their fully qualified class name.
     *
     * This prevents key collisions between different types of models.
     *
     * @param  array<string, array<string, mixed>>  $keys
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
