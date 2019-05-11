<?php

namespace Nuwave\Lighthouse\Execution;

use ReflectionClass;
use ReflectionNamedType;
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
     * Execute a create mutation.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     *         An empty instance of the model that should be created
     * @param  \Illuminate\Support\Collection  $args
     *         The corresponding slice of the input arguments for creating this model
     * @param  \Illuminate\Database\Eloquent\Relations\Relation|null  $parentRelation
     *         If we are in a nested create, we can use this to associate the new model to its parent
     * @return \Illuminate\Database\Eloquent\Model
     */
    public static function executeCreate(Model $model, Collection $args, ?Relation $parentRelation = null): Model
    {
        $reflection = new ReflectionClass($model);

        [$hasMany, $remaining] = self::partitionArgsByRelationType($reflection, $args, HasMany::class);

        [$morphMany, $remaining] = self::partitionArgsByRelationType($reflection, $remaining, MorphMany::class);

        [$hasOne, $remaining] = self::partitionArgsByRelationType($reflection, $remaining, HasOne::class);

        [$morphOne, $remaining] = self::partitionArgsByRelationType($reflection, $remaining, MorphOne::class);

        [$belongsToMany, $remaining] = self::partitionArgsByRelationType($reflection, $remaining, BelongsToMany::class);

        [$morphToMany, $remaining] = self::partitionArgsByRelationType($reflection, $remaining, MorphToMany::class);

        $model = self::saveModelWithPotentialParent($model, $remaining, $parentRelation);

        $createOneToMany = function (array $nestedOperations, string $relationName) use ($model): void {
            /** @var \Illuminate\Database\Eloquent\Relations\HasMany|\Illuminate\Database\Eloquent\Relations\MorphMany $relation */
            $relation = $model->{$relationName}();

            if ($create = $nestedOperations['create'] ?? false) {
                self::handleMultiRelationCreate(new Collection($create), $relation);
            }
        };
        $hasMany->each($createOneToMany);
        $morphMany->each($createOneToMany);

        $createOneToOne = function (array $nestedOperations, string $relationName) use ($model): void {
            /** @var \Illuminate\Database\Eloquent\Relations\HasOne|\Illuminate\Database\Eloquent\Relations\MorphOne $relation */
            $relation = $model->{$relationName}();

            if ($create = $nestedOperations['create'] ?? false) {
                self::handleSingleRelationCreate(new Collection($create), $relation);
            }
        };
        $hasOne->each($createOneToOne);
        $morphOne->each($createOneToOne);

        $createManyToMany = function (array $nestedOperations, string $relationName) use ($model): void {
            /** @var \Illuminate\Database\Eloquent\Relations\BelongsToMany|\Illuminate\Database\Eloquent\Relations\MorphToMany $relation */
            $relation = $model->{$relationName}();

            if ($sync = $nestedOperations['sync'] ?? false) {
                $relation->sync($sync);
            }

            if ($create = $nestedOperations['create'] ?? false) {
                self::handleMultiRelationCreate(new Collection($create), $relation);
            }

            if ($update = $nestedOperations['update'] ?? false) {
                (new Collection($update))->each(function ($singleValues) use ($relation): void {
                    self::executeUpdate(
                        $relation->getModel()->newInstance(),
                        new Collection($singleValues),
                        $relation
                    );
                });
            }

            if ($connect = $nestedOperations['connect'] ?? false) {
                $relation->attach($connect);
            }
        };
        $belongsToMany->each($createManyToMany);
        $morphToMany->each($createManyToMany);

        return $model;
    }

    /**
     * Save a model that maybe has a parent.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  \Illuminate\Support\Collection  $args
     * @param  \Illuminate\Database\Eloquent\Relations\Relation|null  $parentRelation
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected static function saveModelWithPotentialParent(Model $model, Collection $args, ?Relation $parentRelation = null): Model
    {
        [$belongsTo, $remaining] = self::partitionArgsByRelationType(
            new ReflectionClass($model),
            $args,
            BelongsTo::class
        );

        // Use all the remaining attributes and fill the model
        $model->fill(
            $remaining->all()
        );

        $belongsTo->each(function (array $nestedOperations, string $relationName) use ($model): void {
            /** @var \Illuminate\Database\Eloquent\Relations\BelongsTo $relation */
            $relation = $model->{$relationName}();

            if ($create = $nestedOperations['create'] ?? false) {
                $belongsToModel = self::executeCreate(
                    $relation->getModel()->newInstance(),
                    new Collection($create)
                );
                $relation->associate($belongsToModel);
            }

            if ($connect = $nestedOperations['connect'] ?? false) {
                // Inverse can be hasOne or hasMany
                /** @var \Illuminate\Database\Eloquent\Relations\BelongsTo $belongsTo */
                $belongsTo = $model->{$relationName}();
                $belongsTo->associate($connect);
            }

            if ($update = $nestedOperations['update'] ?? false) {
                $belongsToModel = self::executeUpdate(
                    $relation->getModel()->newInstance(),
                    new Collection($update)
                );
                $relation->associate($belongsToModel);
            }

            // We proceed with disconnecting/deleting only if the given $values is truthy.
            // There is no other information to be passed when issuing those operations,
            // but GraphQL forces us to pass some value. It would be unintuitive for
            // the end user if the given value had no effect on the execution.
            if ($nestedOperations['disconnect'] ?? false) {
                $relation->dissociate();
            }

            if ($nestedOperations['delete'] ?? false) {
                $relation->delete();
            }
        });

        if ($parentRelation && ! $parentRelation instanceof BelongsToMany) {
            // If we are already resolving a nested create, we might
            // already have an instance of the parent relation available.
            // In that case, use it to set the current model as a child.
            $parentRelation->save($model);

            return $model;
        }

        $model->save();

        if ($parentRelation instanceof BelongsToMany) {
            $parentRelation->syncWithoutDetaching($model);
        }

        return $model;
    }

    /**
     * Handle the creation with multiple relations.
     *
     * @param  \Illuminate\Support\Collection  $multiValues
     * @param  \Illuminate\Database\Eloquent\Relations\Relation  $relation
     * @return void
     */
    protected static function handleMultiRelationCreate(Collection $multiValues, Relation $relation): void
    {
        $multiValues->each(function ($singleValues) use ($relation): void {
            self::handleSingleRelationCreate(new Collection($singleValues), $relation);
        });
    }

    /**
     * Handle the creation with a single relation.
     *
     * @param  \Illuminate\Support\Collection  $singleValues
     * @param  \Illuminate\Database\Eloquent\Relations\Relation  $relation
     * @return void
     */
    protected static function handleSingleRelationCreate(Collection $singleValues, Relation $relation): void
    {
        self::executeCreate(
            $relation->getModel()->newInstance(),
            $singleValues,
            $relation
        );
    }

    /**
     * Execute an update mutation.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     *         An empty instance of the model that should be updated
     * @param  \Illuminate\Support\Collection  $args
     *         The corresponding slice of the input arguments for updating this model
     * @param  \Illuminate\Database\Eloquent\Relations\Relation|null  $parentRelation
     *         If we are in a nested update, we can use this to associate the new model to its parent
     * @return \Illuminate\Database\Eloquent\Model
     */
    public static function executeUpdate(Model $model, Collection $args, ?Relation $parentRelation = null): Model
    {
        $id = $args->pull('id')
            ?? $args->pull(
                $model->getKeyName()
            );

        $model = $model->newQuery()->findOrFail($id);

        $reflection = new ReflectionClass($model);

        [$hasMany, $remaining] = self::partitionArgsByRelationType($reflection, $args, HasMany::class);

        [$morphMany, $remaining] = self::partitionArgsByRelationType($reflection, $remaining, MorphMany::class);

        [$hasOne, $remaining] = self::partitionArgsByRelationType($reflection, $remaining, HasOne::class);

        [$morphOne, $remaining] = self::partitionArgsByRelationType($reflection, $remaining, MorphOne::class);

        [$belongsToMany, $remaining] = self::partitionArgsByRelationType($reflection, $remaining, BelongsToMany::class);

        [$morphToMany, $remaining] = self::partitionArgsByRelationType($reflection, $remaining, MorphToMany::class);

        $model = self::saveModelWithPotentialParent($model, $remaining, $parentRelation);

        $updateOneToMany = function (array $nestedOperations, string $relationName) use ($model): void {
            /** @var \Illuminate\Database\Eloquent\Relations\HasMany|\Illuminate\Database\Eloquent\Relations\MorphMany $relation */
            $relation = $model->{$relationName}();

            if ($create = $nestedOperations['create'] ?? false) {
                self::handleMultiRelationCreate(new Collection($create), $relation);
            }

            if ($update = $nestedOperations['update'] ?? false) {
                (new Collection($update))->each(function ($singleValues) use ($relation): void {
                    self::executeUpdate(
                        $relation->getModel()->newInstance(),
                        new Collection($singleValues),
                        $relation
                    );
                });
            }

            if ($delete = $nestedOperations['delete'] ?? false) {
                $relation->getModel()::destroy($delete);
            }
        };
        $hasMany->each($updateOneToMany);
        $morphMany->each($updateOneToMany);

        $updateOneToOne = function (array $nestedOperations, string $relationName) use ($model): void {
            /** @var \Illuminate\Database\Eloquent\Relations\HasOne|\Illuminate\Database\Eloquent\Relations\MorphOne $relation */
            $relation = $model->{$relationName}();

            if ($create = $nestedOperations['create'] ?? false) {
                self::handleSingleRelationCreate(new Collection($create), $relation);
            }

            if ($update = $nestedOperations['update'] ?? false) {
                self::executeUpdate(
                    $relation->getModel()->newInstance(),
                    new Collection($update),
                    $relation
                );
            }

            if ($delete = $nestedOperations['delete'] ?? false) {
                $relation->getModel()::destroy($delete);
            }
        };
        $hasOne->each($updateOneToOne);
        $morphOne->each($updateOneToOne);

        $updateManyToMany = function (array $nestedOperations, string $relationName) use ($model): void {
            /** @var \Illuminate\Database\Eloquent\Relations\BelongsToMany|\Illuminate\Database\Eloquent\Relations\MorphToMany $relation */
            $relation = $model->{$relationName}();

            if ($sync = $nestedOperations['sync'] ?? false) {
                $relation->sync($sync);
            }

            if ($create = $nestedOperations['create'] ?? false) {
                self::handleMultiRelationCreate(new Collection($create), $relation);
            }

            if ($update = $nestedOperations['update'] ?? false) {
                (new Collection($update))->each(function ($singleValues) use ($relation): void {
                    self::executeUpdate(
                        $relation->getModel()->newInstance(),
                        new Collection($singleValues),
                        $relation
                    );
                });
            }

            if ($delete = $nestedOperations['delete'] ?? false) {
                $relation->detach($delete);
                $relation->getModel()::destroy($delete);
            }

            if ($connect = $nestedOperations['connect'] ?? false) {
                $relation->attach($connect);
            }

            if ($disconnect = $nestedOperations['disconnect'] ?? false) {
                $relation->detach($disconnect);
            }
        };
        $belongsToMany->each($updateManyToMany);
        $morphToMany->each($updateManyToMany);

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
    protected static function partitionArgsByRelationType(ReflectionClass $modelReflection, Collection $args, string $relationClass): Collection
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

                if (! $returnType instanceof ReflectionNamedType) {
                    return false;
                }

                return $relationClass === $returnType->getName();
            }
        );
    }
}
