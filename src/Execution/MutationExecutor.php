<?php

namespace Nuwave\Lighthouse\Execution;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class MutationExecutor
{
    public static function executeCreate(Model $model, Collection $args, HasMany $parentRelation = null): Model
    {
        list($belongsTo, $remaining) = self::extractBelongsToArgs($model, $args);
        list($hasMany, $remaining) = self::extractHasManyArgs($model, $remaining);

        $model->fill($remaining->all());

        $belongsTo->each(function ($value, $key) use ($model) {
            $model->{$key}()->associate($value);
        });
        
        $parentRelation
            ? $parentRelation->save($model)
            : $model->save();
        
        $hasMany->each(function ($nestedOperations, $key) use ($model) {
            /** @var HasMany $relation */
            $relation = $model->{$key}();
            
            collect($nestedOperations)->each(function ($values, $operationKey) use ($relation) {
                if ($operationKey === 'create') {
                    self::handleHasManyCreate(collect($values), $relation);
                }
            });
        });
        
        return $model;
    }
    
    protected static function handleHasManyCreate(Collection $multiValues, HasMany $relation)
    {
        $multiValues->each(function ($singleValues) use ($relation) {
            self::executeCreate($relation->getModel()->newInstance(), collect($singleValues), $relation);
        });
    }
    
    public static function executeUpdate(Model $model, Collection $args, HasMany $parentRelation = null): Model
    {
        list($belongsTo, $remaining) = self::extractBelongsToArgs($model, $args);
        list($hasMany, $remaining) = self::extractHasManyArgs($model, $remaining);
        
        $model = $model->newQuery()->findOrFail($args->pull('id'));
        $model->fill($remaining->all());
        
        $belongsTo->each(function ($value, $key) use ($model) {
            $model->{$key}()->associate($value);
        });
        
        $parentRelation
            ? $parentRelation->save($model)
            : $model->save();
        
        $hasMany->each(function ($nestedOperations, $key) use ($model) {
            /** @var HasMany $relation */
            $relation = $model->{$key}();
            
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
    
    protected static function extractBelongsToArgs(Model $model, Collection $args): Collection
    {
        return $args->partition(function ($value, $key) use ($model) {
            return method_exists($model, $key) && ($model->{$key}() instanceof BelongsTo);
        });
    }
    
    protected static function extractHasManyArgs(Model $model, Collection $args): Collection
    {
        return $args->partition(function ($value, $key) use ($model) {
            return method_exists($model, $key) && ($model->{$key}() instanceof HasMany);
        });
    }
}
