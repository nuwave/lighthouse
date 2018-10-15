<?php

namespace Nuwave\Lighthouse\Execution;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MutationExecutor
{
    /**
     * @param Model $model An empty instance of the model that should be created.
     * @param Collection $args The corresponding slice of the input arguments for creating this model.
     * @param HasMany|null $parentRelation If we are in a nested create, we can use this to associate the new model to its parent.
     *
     * @return Model
     */
    public static function executeCreate(Model $model, Collection $args, HasMany $parentRelation = null): Model
    {
        list($hasMany, $remaining) = self::extractHasManyArgs($model, $args);

        $model = self::saveModelWithBelongsTo($model, $remaining, $parentRelation);

        $hasMany->each(function ($nestedOperations, $relationName) use ($model) {
            /** @var HasMany $relation */
            $relation = $model->{$relationName}();

            collect($nestedOperations)->each(function ($values, string $operationKey) use ($relation) {
                if ($operationKey === 'create') {
                    self::handleHasManyCreate(collect($values), $relation);
                }
            });
        });

        return $model;
    }


    /**
     * @param Model $model
     * @param Collection $remaining
     * @param HasMany $parentRelation
     *
     * @return Model
     */
    protected static function saveModelWithBelongsTo(Model $model, Collection $remaining, HasMany $parentRelation = null): Model
    {
        list($belongsTo, $remaining) = self::extractBelongsToArgs($model, $remaining);

        // Use all the remaining attributes and fill the model
        $model->fill(
            $remaining->all()
        );

        $belongsTo->each(function ($relatedId, string $relationName) use ($model) {
            /** @var BelongsTo $belongsTo */
            $belongsTo = $model->{$relationName}();

            $belongsTo->associate($relatedId);
        });

        // If we are already resolving a nested create, we might
        // already have an instance of the parent relation available.
        // In that case, use it to set the current model as a child.
        $parentRelation
            ? $parentRelation->save($model)
            : $model->save();

        return $model;
    }

    protected static function handleHasManyCreate(Collection $multiValues, HasMany $relation)
    {
        $multiValues->each(function ($singleValues) use ($relation) {
            self::executeCreate($relation->getModel()->newInstance(), collect($singleValues), $relation);
        });
    }

    /**
     * @param Model $model An empty instance of the model that should be updated.
     * @param Collection $args The corresponding slice of the input arguments for updating this model.
     * @param HasMany|null $parentRelation If we are in a nested update, we can use this to associate the new model to its parent.
     *
     * @throws ModelNotFoundException
     *
     * @return Model
     */
    public static function executeUpdate(Model $model, Collection $args, HasMany $parentRelation = null): Model
    {
        $model = $model->newQuery()->findOrFail(
            $args->pull('id')
        );

        list($hasMany, $remaining) = self::extractHasManyArgs($model, $args);

        $model = self::saveModelWithBelongsTo($model, $remaining, $parentRelation);

        $hasMany->each(function ($nestedOperations, $relationName) use ($model) {
            /** @var HasMany $relation */
            $relation = $model->{$relationName}();

            collect($nestedOperations)->each(function ($values, $operationKey) use ($relation) {
                if ($operationKey === 'create') {
                    self::handleHasManyCreate(collect($values), $relation);
                }

                if ($operationKey === 'update') {
                    collect($values)->each(function ($singleValues) use ($relation) {
                        self::executeUpdate($relation->getModel()->newInstance(), collect($singleValues), $relation);
                    });
                }

                if ($operationKey === 'delete') {
                    $relation->getModel()::destroy($values);
                }
            });
        });

        return $model;
    }

    /**
     * Extract all the arguments that are named the same as a BelongsTo relationship on the model.
     *
     * For example, if the args array looks like this:
     *
     * ['user' => 123, 'name' => 'Ralf']
     *
     * and the model has a method "user" that returns a BelongsTo relationship,
     * the result will be:
     * [
     *   ['user' => 123],
     *   ['name' => 'Ralf']
     * ]
     *
     * @param Model $model
     * @param Collection $args
     *
     * @return Collection
     */
    protected static function extractBelongsToArgs(Model $model, Collection $args): Collection
    {
        return $args->partition(function ($value, $key) use ($model) {
            return method_exists($model, $key) && ($model->{$key}() instanceof BelongsTo);
        });
    }

    /**
     * Extract all the arguments that are named the same as a HasMany relationship on the model.
     *
     * For example, if the args array looks like this:
     *
     * [
     *  'comments' =>
     *    ['foo' => 'Bar'],
     *  'name' => 'Ralf',
     * ]
     *
     * and the model has a method "comments" that returns a HasMany relationship,
     * the result will be:
     * [
     *   [
     *    'comments' =>
     *      ['foo' => 'Bar'],
     *   ],
     *   [
     *    'name' => 'Ralf',
     *   ]
     * ]
     *
     * @param Model $model
     * @param Collection $args
     *
     * @return Collection
     */
    protected static function extractHasManyArgs(Model $model, Collection $args): Collection
    {
        return $args->partition(function ($value, $key) use ($model) {
            return method_exists($model, $key) && ($model->{$key}() instanceof HasMany);
        });
    }
}
