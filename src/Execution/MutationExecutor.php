<?php

namespace Nuwave\Lighthouse\Execution;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MutationExecutor
{
    /**
     * @param  \Illuminate\Database\Eloquent\Model  $model
     *         An empty instance of the model that should be created
     * @param  \Illuminate\Support\Collection  $args
     *         The corresponding slice of the input arguments for creating this model
     * @param  \Illuminate\Database\Eloquent\Relations\Relation|null  $parentRelation
     *         If we are in a nested create, we can use this to associate the new model to its parent
     * @return \Illuminate\Database\Eloquent\Model
     */
    public static function executeCreate(Model $model, Collection $args, Relation $parentRelation = null): Model
    {
        $reflection = new \ReflectionClass($model);

        [$hasMany, $remaining] = self::partitionArgsByRelationType($reflection, $args, HasMany::class);

        [$morphMany, $remaining] = self::partitionArgsByRelationType($reflection, $remaining, MorphMany::class);

        [$hasOne, $remaining] = self::partitionArgsByRelationType($reflection, $remaining, HasOne::class);

        [$belongsToMany, $remaining] = self::partitionArgsByRelationType($reflection, $remaining, BelongsToMany::class);

        [$morphOne, $remaining] = self::partitionArgsByRelationType($reflection, $remaining, MorphOne::class);

        [$morphToMany, $remaining] = self::partitionArgsByRelationType($reflection, $remaining, MorphToMany::class);

        $model = self::saveModelWithBelongsTo($model, $remaining, $parentRelation);

        $hasMany->each(function (array $nestedOperations, string $relationName) use ($model): void {
            /** @var \Illuminate\Database\Eloquent\Relations\HasMany $relation */
            $relation = $model->{$relationName}();

            collect($nestedOperations)->each(function ($values, string $operationKey) use ($relation): void {
                if ($operationKey === 'create') {
                    self::handleMultiRelationCreate(collect($values), $relation);
                }
            });
        });

        $hasOne->each(function (array $nestedOperations, string $relationName) use ($model): void {
            /** @var \Illuminate\Database\Eloquent\Relations\HasOne $relation */
            $relation = $model->{$relationName}();

            collect($nestedOperations)->each(function ($values, string $operationKey) use ($relation): void {
                if ($operationKey === 'create') {
                    self::handleSingleRelationCreate(collect($values), $relation);
                }
            });
        });

        $morphMany->each(function (array $nestedOperations, string $relationName) use ($model): void {
            /** @var \Illuminate\Database\Eloquent\Relations\MorphMany $relation */
            $relation = $model->{$relationName}();

            collect($nestedOperations)->each(function ($values, string $operationKey) use ($relation): void {
                if ($operationKey === 'create') {
                    self::handleMultiRelationCreate(collect($values), $relation);
                }
            });
        });

        $morphOne->each(function (array $nestedOperations, string $relationName) use ($model): void {
            /** @var \Illuminate\Database\Eloquent\Relations\MorphOne $relation */
            $relation = $model->{$relationName}();

            collect($nestedOperations)->each(function ($values, string $operationKey) use ($relation): void {
                if ($operationKey === 'create') {
                    self::handleSingleRelationCreate(collect($values), $relation);
                }
            });
        });

        $belongsToMany->each(function (array $nestedOperations, string $relationName) use ($model): void {
            /** @var \Illuminate\Database\Eloquent\Relations\BelongsToMany $relation */
            $relation = $model->{$relationName}();

            collect($nestedOperations)->each(function ($values, string $operationKey) use ($relation): void {
                if ($operationKey === 'create') {
                    self::handleMultiRelationCreate(collect($values), $relation);
                }

                if ($operationKey === 'connect') {
                    $relation->attach($values);
                }
            });
        });

        $morphToMany->each(function (array $nestedOperations, string $relationName) use ($model): void {
            /** @var \Illuminate\Database\Eloquent\Relations\HasMany $relation */
            $relation = $model->{$relationName}();

            collect($nestedOperations)->each(function ($values, string $operationKey) use ($relation): void {
                if ($operationKey === 'create') {
                    self::handleMultiRelationCreate(collect($values), $relation);
                }
            });
        });

        return $model;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  \Illuminate\Support\Collection  $args
     * @param  \Illuminate\Database\Eloquent\Relations\Relation|null  $parentRelation
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected static function saveModelWithBelongsTo(Model $model, Collection $args, Relation $parentRelation = null): Model
    {
        $reflection = new \ReflectionClass($model);
        [$belongsTo, $remaining] = self::partitionArgsByRelationType($reflection, $args, BelongsTo::class);

        // Use all the remaining attributes and fill the model
        $model->fill(
            $remaining->all()
        );

        $belongsTo->each(function (array $nestedOperations, string $relationName) use ($model): void {
            /** @var \Illuminate\Database\Eloquent\Relations\BelongsTo $relation */
            $relation = $model->{$relationName}();

            collect($nestedOperations)->each(function ($values, string $operationKey) use ($relation, $model, $relationName): void {
                if ($operationKey === 'create') {
                    $belongsToModel = self::executeCreate($relation->getModel()->newInstance(), collect($values));
                    $relation->associate($belongsToModel);
                }

                if ($operationKey === 'connect') {
                    // Inverse can be hasOne or hasMany
                    /** @var \Illuminate\Database\Eloquent\Relations\BelongsTo $belongsTo */
                    $belongsTo = $model->{$relationName}();
                    $belongsTo->associate($values);
                }

                if ($operationKey === 'delete') {
                    $relation->getModel()::destroy($values);
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
     * @param  \Illuminate\Support\Collection  $multiValues
     * @param  \Illuminate\Database\Eloquent\Relations\Relation  $relation
     * @return void
     */
    protected static function handleMultiRelationCreate(Collection $multiValues, Relation $relation): void
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
     * @param  \Illuminate\Support\Collection  $singleValues
     * @param  \Illuminate\Database\Eloquent\Relations\Relation  $relation
     * @return void
     */
    protected static function handleSingleRelationCreate(Collection $singleValues, Relation $relation): void
    {
        self::executeCreate(
            $relation->getModel()->newInstance(),
            collect($singleValues),
            $relation
        );
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Model  $model
     *         An empty instance of the model that should be updated
     * @param  \Illuminate\Support\Collection  $args
     *         The corresponding slice of the input arguments for updating this model
     * @param  \Illuminate\Database\Eloquent\Relations\HasMany|null  $parentRelation
     *         If we are in a nested update, we can use this to associate the new model to its parent
     * @return \Illuminate\Database\Eloquent\Model
     */
    public static function executeUpdate(Model $model, Collection $args, ?HasMany $parentRelation = null): Model
    {
        $id = $args->pull('id')
            ?? $args->pull(
                $model->getKeyName()
            );

        $model = $model->newQuery()->findOrFail($id);

        $reflection = new \ReflectionClass($model);

        [$hasMany, $remaining] = self::partitionArgsByRelationType($reflection, $args, HasMany::class);

        [$morphMany, $remaining] = self::partitionArgsByRelationType($reflection, $remaining, MorphMany::class);

        [$hasOne, $remaining] = self::partitionArgsByRelationType($reflection, $remaining, HasOne::class);

        [$belongsToMany, $remaining] = self::partitionArgsByRelationType($reflection, $remaining, BelongsToMany::class);

        [$morphOne, $remaining] = self::partitionArgsByRelationType($reflection, $remaining, MorphOne::class);

        [$morphToMany, $remaining] = self::partitionArgsByRelationType($reflection, $remaining, MorphToMany::class);

        $model = self::saveModelWithBelongsTo($model, $remaining, $parentRelation);

        $hasMany->each(function (array $nestedOperations, string $relationName) use ($model): void {
            /** @var \Illuminate\Database\Eloquent\Relations\HasMany $relation */
            $relation = $model->{$relationName}();

            collect($nestedOperations)->each(function ($values, string $operationKey) use ($relation): void {
                if ($operationKey === 'create') {
                    self::handleMultiRelationCreate(collect($values), $relation);
                }

                if ($operationKey === 'update') {
                    collect($values)->each(function ($singleValues) use ($relation): void {
                        self::executeUpdate($relation->getModel()->newInstance(), collect($singleValues), $relation);
                    });
                }

                if ($operationKey === 'delete') {
                    $relation->getModel()::destroy($values);
                }
            });
        });

        $hasOne->each(function (array $nestedOperations, string $relationName) use ($model): void {
            /** @var \Illuminate\Database\Eloquent\Relations\HasOne $relation */
            $relation = $model->{$relationName}();

            collect($nestedOperations)->each(function ($values, string $operationKey) use ($relation): void {
                if ($operationKey === 'create') {
                    self::handleSingleRelationCreate(collect($values), $relation);
                }

                if ($operationKey === 'update') {
                    collect($values)->each(function ($singleValues) use ($relation): void {
                        self::executeUpdate($relation->getModel()->newInstance(), collect($singleValues), $relation);
                    });
                }

                if ($operationKey === 'delete') {
                    $relation->getModel()::destroy($values);
                }
            });
        });

        $morphMany->each(function (array $nestedOperations, string $relationName) use ($model): void {
            /** @var \Illuminate\Database\Eloquent\Relations\MorphMany $relation */
            $relation = $model->{$relationName}();

            collect($nestedOperations)->each(function ($values, string $operationKey) use ($relation): void {
                if ($operationKey === 'create') {
                    self::handleMultiRelationCreate(collect($values), $relation);
                }
                if ($operationKey === 'update') {
                    collect($values)->each(function ($singleValues) use ($relation): void {
                        self::executeUpdate($relation->getModel()->newInstance(), collect($singleValues), $relation);
                    });
                }

                if ($operationKey === 'delete') {
                    $relation->getModel()::destroy($values);
                }
            });
        });

        $morphOne->each(function (array $nestedOperations, string $relationName) use ($model): void {
            /** @var \Illuminate\Database\Eloquent\Relations\MorphOne $relation */
            $relation = $model->{$relationName}();

            collect($nestedOperations)->each(function ($values, string $operationKey) use ($relation): void {
                if ($operationKey === 'create') {
                    self::handleSingleRelationCreate(collect($values), $relation);
                }

                if ($operationKey === 'update') {
                    collect($values)->each(function ($singleValues) use ($relation): void {
                        self::executeUpdate($relation->getModel()->newInstance(), collect($singleValues), $relation);
                    });
                }

                if ($operationKey === 'delete') {
                    $relation->getModel()::destroy($values);
                }
            });
        });

        $belongsToMany->each(function (array $nestedOperations, string $relationName) use ($model): void {
            /** @var \Illuminate\Database\Eloquent\Relations\BelongsToMany $relation */
            $relation = $model->{$relationName}();

            collect($nestedOperations)->each(function ($values, string $operationKey) use ($relation): void {
                if ($operationKey === 'create') {
                    self::handleMultiRelationCreate(collect($values), $relation);
                }

                if ($operationKey === 'update') {
                    collect($values)->each(function ($singleValues) use ($relation): void {
                        self::executeUpdate($relation->getModel()->newInstance(), collect($singleValues), $relation);
                    });
                }

                if ($operationKey === 'delete') {
                    $relation->getModel()::destroy($values);
                }

                if ($operationKey === 'connect') {
                    $relation->attach($values);
                }
            });
        });

        $morphToMany->each(function (array $nestedOperations, string $relationName) use ($model): void {
            /** @var \Illuminate\Database\Eloquent\Relations\HasMany $relation */
            $relation = $model->{$relationName}();

            collect($nestedOperations)->each(function ($values, string $operationKey) use ($relation): void {
                if ($operationKey === 'create') {
                    self::handleMultiRelationCreate(collect($values), $relation);
                }
                if ($operationKey === 'update') {
                    collect($values)->each(function ($singleValues) use ($relation): void {
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
     * @param  \ReflectionClass  $modelReflection
     * @param  \Illuminate\Support\Collection  $args
     * @param  string  $relationClass
     * @return \Illuminate\Support\Collection  [relationshipArgs, remainingArgs]
     */
    protected static function partitionArgsByRelationType(\ReflectionClass $modelReflection, Collection $args, string $relationClass): Collection
    {
        return $args->partition(
            function ($value, string $key) use ($modelReflection, $relationClass): bool {
                if (! $modelReflection->hasMethod($key)) {
                    return false;
                }

                $relationMethodCandidate = $modelReflection->getMethod($key);
                if (! $returnType = $relationMethodCandidate->getReturnType()) {
                    return false;
                }

                if (! $returnType instanceof \ReflectionNamedType) {
                    return false;
                }

                return $relationClass === $returnType->getName();
            }
        );
    }
}
