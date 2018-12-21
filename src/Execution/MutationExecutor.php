<?php

namespace Nuwave\Lighthouse\Execution;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MutationExecutor
{
    /**
     * @param Model         $model          an empty instance of the model that should be created
     * @param Collection    $args           the corresponding slice of the input arguments for creating this model
     * @param Relation|null $parentRelation if we are in a nested create, we can use this to associate the new model to its parent
     *
     * @return Model
     */
    public static function executeCreate(Model $model, Collection $args, Relation $parentRelation = null): Model
    {
        $reflection = new \ReflectionClass($model);
        list($hasMany, $remaining) = self::partitionArgsByRelationType($reflection, $args, HasMany::class);

        list($morphMany, $remaining) = self::extractMorphManyArgs($model, $remaining);

        list($hasOne, $remaining) = self::extractHasOneArgs($model, $remaining);

        list($belongsToMany, $remaining) = self::extractBelongsToManyArgs($model, $remaining);

        list($morphOne, $remaining) = self::extractMorphOneArgs($model, $remaining);

        $model = self::saveModelWithBelongsTo($model, $remaining, $parentRelation);

        $hasMany->each(function ($nestedOperations, string $relationName) use ($model): void {
            /** @var HasMany $relation */
            $relation = $model->{$relationName}();

            collect($nestedOperations)->each(function ($values, string $operationKey) use ($relation) {
                if ('create' === $operationKey) {
                    self::handleHasManyCreate(collect($values), $relation);
                }

                if ('create' === $operationKey) {
                    self::handleHasManyCreate(collect($values), $relation);
                }
            });
        });

        $hasOne->each(function ($nestedOperations, string $relationName) use ($model) {
            /** @var HasOne $relation */
            $relation = $model->{$relationName}();

            collect($nestedOperations)->each(function ($values, string $operationKey) use ($relation) {
                if ('create' === $operationKey) {
                    self::handleHasOneCreate(collect($values), $relation);
                }
            });
        });

        $morphMany->each(function ($nestedOperations, string $relationName) use ($model) {
            /** @var MorphMany $relation */
            $relation = $model->{$relationName}();

            collect($nestedOperations)->each(function ($values, string $operationKey) use ($relation) {
                if ('create' === $operationKey) {
                    self::handleMorphManyCreate(collect($values), $relation);
                }
            });
        });

        $morphOne->each(function ($nestedOperations, string $relationName) use ($model) {
            /** @var MorphOne $relation */
            $relation = $model->{$relationName}();

            collect($nestedOperations)->each(function ($values, string $operationKey) use ($relation) {
                if ('create' === $operationKey) {
                    self::handleMorphOneCreate(collect($values), $relation);
                }
            });
        });

        $belongsToMany->each(function ($nestedOperations, string $relationName) use ($model) {
            /** @var BelongsToMany $relation */
            $relation = $model->{$relationName}();

            collect($nestedOperations)->each(function ($values, string $operationKey) use ($relation) {
                if ('create' === $operationKey) {
                    self::handleBelongsToManyCreate(collect($values), $relation);
                }
            });
        });

        return $model;
    }

    /**
     * @param Model      $model
     * @param Collection $remaining
     * @param Relation   $parentRelation
     *
     * @return Model
     */
    protected static function saveModelWithBelongsTo(Model $model, Collection $remaining, Relation $parentRelation = null): Model
    {
        $reflection = new \ReflectionClass($model);
        list($belongsTo, $remaining) = self::partitionArgsByRelationType($reflection, $remaining,BelongsTo::class);

        // Use all the remaining attributes and fill the model
        $model->fill(
            $remaining->all()
        );

        $belongsTo->each(function ($relatedId, string $relationName) use ($model): void {
            /** @var BelongsTo $belongsTo */
            $relation = $model->{$relationName}();

            collect($nestedOperations)->each(function ($values, string $operationKey) use ($relation) {
                if ('create' === $operationKey) {
                    // Inverse can be hasOne or hasMany
                    $belongsToModel = self::executeCreate($relation->getModel()->newInstance(), collect($values));
                    $relation->associate($belongsToModel);
                }
            });
        });

        // If we are already resolving a nested create, we might
        // already have an instance of the parent relation available.
        // In that case, use it to set the current model as a child.
        $parentRelation
            ? $parentRelation->save($model)
            : $model->save();

        return $model;
    }

    /**
     * @param Collection $multiValues
     * @param HasMany    $relation
     */
    protected static function handleHasManyCreate(Collection $multiValues, HasMany $relation)
    {
        $multiValues->each(function ($singleValues) use ($relation): void {
            self::executeCreate(
                $relation->getModel()->newInstance(),
                collect($singleValues),
                $relation
            );
        });
    }

    /**
    /**
     * @param Collection $multiValues
     * @param HasMany    $relation
     */
    protected static function handleBelongsToManyCreate(Collection $multiValues, BelongsToMany $relation)
    {
        $multiValues->each(function ($singleValues) use ($relation) {
            self::executeCreate($relation->getModel()->newInstance(), collect($singleValues), $relation);
        });
    }

    /**
     * @param Collection $multiValues
     * @param MorphMany  $relation
     */
    protected static function handleMorphManyCreate(Collection $multiValues, MorphMany $relation)
    {
        $multiValues->each(function ($singleValues) use ($relation) {
            self::executeCreate($relation->getModel()->newInstance(), collect($singleValues), $relation);
        });
    }

    /**
     * @param Collection $singleValues
     * @param HasOne     $relation
     */
    protected static function handleHasOneCreate(Collection $singleValues, HasOne $relation)
    {
        self::executeCreate($relation->getModel()->newInstance(), collect($singleValues), $relation);
    }

    /**
     * @param Collection $singleValues
     * @param MorphOne   $relation
     */
    protected static function handleMorphOneCreate(Collection $singleValues, MorphOne $relation)
    {
        self::executeCreate($relation->getModel()->newInstance(), collect($singleValues), $relation);
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
    public static function executeUpdate(Model $model, Collection $args, ?HasMany $parentRelation = null): Model
    {
        $id = $args->pull('id')
            ?? $args->pull(
                $model->getKeyName()
            );

        $model = $model->newQuery()->findOrFail($id);

        $reflection = new \ReflectionClass($model);
        list($hasMany, $remaining) = self::partitionArgsByRelationType($reflection, $args, HasMany::class);

        $model = self::saveModelWithBelongsTo($model, $remaining, $parentRelation);

        $hasMany->each(function ($nestedOperations, string $relationName) use ($model): void {
            /** @var HasMany $relation */
            $relation = $model->{$relationName}();

            collect($nestedOperations)->each(function ($values, string $operationKey) use ($relation): void {
                if ('create' === $operationKey) {
                    self::handleHasManyCreate(collect($values), $relation);
                }

                if ('update' === $operationKey) {
                    collect($values)->each(function ($singleValues) use ($relation) {
                        self::executeUpdate($relation->getModel()->newInstance(), collect($singleValues), $relation);
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
     * For example, if the args input looks like this:
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
     * @param \ReflectionClass $modelReflection
     * @param Collection       $args
     * @param string           $relationClass
     *
     * @return Collection [relationshipArgs, remainingArgs]
     */
    protected static function partitionArgsByRelationType(\ReflectionClass $modelReflection, Collection $args, string $relationClass): Collection
    {
        return $args->partition(
            function ($value, string $key) use ($modelReflection, $relationClass): bool {
                if(! $modelReflection->hasMethod($key)){
                    return false;
                }

                $relationMethodCandidate = $modelReflection->getMethod($key);
                if(!$returnType = $relationMethodCandidate->getReturnType()){
                    return false;
                }

                if(! $returnType instanceof \ReflectionNamedType){
                    return false;
                }

                return $returnType->getName() === $relationClass;
            }
        );
    }

    /**
     * @param Model      $model
     * @param Collection $args
     *
     * @return Collection
     */
    protected static function extractMorphManyArgs(Model $model, Collection $args): Collection
    {
        return $args->partition(function ($value, $key) use ($model) {
            return method_exists($model, $key) && ($model->{$key}() instanceof MorphMany);
        });
    }

    /**
     * @param Model      $model
     * @param Collection $args
     *
     * @return Collection
     */
    protected static function extractHasOneArgs(Model $model, Collection $args): Collection
    {
        return $args->partition(function ($value, $key) use ($model) {
            return method_exists($model, $key) && ($model->{$key}() instanceof HasOne);
        });
    }
}
