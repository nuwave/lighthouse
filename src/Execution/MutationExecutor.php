<?php

namespace Nuwave\Lighthouse\Execution;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MutationExecutor
{
    /**
     * @param Model        $model          an empty instance of the model that should be created
     * @param Collection   $args           the corresponding slice of the input arguments for creating this model
     * @param HasMany|null $parentRelation if we are in a nested create, we can use this to associate the new model to its parent
     *
     * @return Model
     */
    public static function executeCreate(Model $model, Collection $args, HasMany $parentRelation = null): Model
    {
        $reflection = new \ReflectionClass($model);
        list($hasMany, $remaining) = self::partitionArgsByRelationType($reflection, $args, HasMany::class);

        $model = self::saveModelWithBelongsTo($model, $remaining, $parentRelation);

        $hasMany->each(function ($nestedOperations, $relationName) use ($model) {
            /** @var HasMany $relation */
            $relation = $model->{$relationName}();

            collect($nestedOperations)->each(function ($values, string $operationKey) use ($relation) {
                if ('create' === $operationKey) {
                    self::handleHasManyCreate(collect($values), $relation);
                }
            });
        });

        return $model;
    }

    /**
     * @param Model      $model
     * @param Collection $remaining
     * @param HasMany    $parentRelation
     *
     * @return Model
     */
    protected static function saveModelWithBelongsTo(Model $model, Collection $remaining, HasMany $parentRelation = null): Model
    {
        $reflection = new \ReflectionClass($model);
        list($belongsTo, $remaining) = self::partitionArgsByRelationType($reflection, $remaining,BelongsTo::class);

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
            self::executeCreate(
                $relation->getModel()->newInstance(),
                collect($singleValues),
                $relation
            );
        });
    }

    /**
     * @param Model        $model          an empty instance of the model that should be updated
     * @param Collection   $args           the corresponding slice of the input arguments for updating this model
     * @param HasMany|null $parentRelation if we are in a nested update, we can use this to associate the new model to its parent
     *
     * @throws ModelNotFoundException
     *
     * @return Model
     */
    public static function executeUpdate(Model $model, Collection $args, HasMany $parentRelation = null): Model
    {
        $id = $args->pull('id')
            ?? $args->pull(
                $model->getKeyName()
            );

        $model = $model->newQuery()->findOrFail($id);

        $reflection = new \ReflectionClass($model);
        list($hasMany, $remaining) = self::partitionArgsByRelationType($reflection, $args, HasMany::class);

        $model = self::saveModelWithBelongsTo($model, $remaining, $parentRelation);

        $hasMany->each(function ($nestedOperations, string $relationName) use ($model) {
            /** @var HasMany $relation */
            $relation = $model->{$relationName}();

            collect($nestedOperations)->each(function ($values, string $operationKey) use ($relation) {
                if ('create' === $operationKey) {
                    self::handleHasManyCreate(collect($values), $relation);
                }

                if ('update' === $operationKey) {
                    collect($values)->each(function ($singleValues) use ($relation) {
                        self::executeUpdate(
                            $relation->getModel()->newInstance(),
                            collect($singleValues),
                            $relation
                        );
                    });
                }

                if ('delete' === $operationKey) {
                    $relation->getModel()::destroy($values);
                }
            });
        });

        return $model;
    }

    /**
     * Extract all the arguments that correspond to a relation of a certain type on the model.
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
     * @param \ReflectionClass $model
     * @param Collection       $args
     * @param string           $relationClass
     *
     * @return Collection
     */
    protected static function partitionArgsByRelationType(\ReflectionClass $model, Collection $args, string $relationClass): Collection
    {
        return $args->partition(function ($value, string $key) use ($model, $relationClass) {
            if(! $model->hasMethod($key)){
                return false;
            }

            $relationMethodCandidate = $model->getMethod($key);
            if(!$returnType = $relationMethodCandidate->getReturnType()){
                return false;
            }

            if(! $returnType instanceof \ReflectionNamedType){
                return false;
            }

            return $returnType->getName() === $relationClass;
        });
    }
}
