<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution\Arguments;

use GraphQL\Error\Error;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Relation;

class ParentRelationScope
{
    public const UNRELATED_MODEL = 'Cannot modify a model that is not related to the given parent';

    /** @param  array<mixed>  $values */
    public static function unrelatedModel(array $values): string
    {
        $valueList = implode(', ', $values);

        return self::UNRELATED_MODEL . ": {$valueList}.";
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Relations\Relation<\Illuminate\Database\Eloquent\Model>|null  $relation
     *
     * @phpstan-assert-if-true !null $relation
     */
    public static function applies(?Relation $relation): bool
    {
        // TODO remove the config check with v7
        return $relation !== null
            // BelongsTo update and upsert attach unrelated models by design
            && ! $relation instanceof BelongsTo
            && config('lighthouse.nested_mutations_scope_to_parent');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Relations\Relation<\Illuminate\Database\Eloquent\Model>|null  $relation
     * @param  int|string  $id
     */
    public static function findOrFail(?Relation $relation, Model $model, mixed $id): Model
    {
        if (! self::applies($relation)) {
            return $model->newQuery()->findOrFail($id);
        }

        return self::findMany($relation, [$id])->firstOrFail();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Relations\Relation<\Illuminate\Database\Eloquent\Model>  $relation
     * @param  array<mixed>  $ids
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model>
     */
    public static function findMany(Relation $relation, array $ids): EloquentCollection
    {
        if (! self::applies($relation)) {
            $related = $relation->getRelated();

            return $related->whereIn($related->getKeyName(), $ids)->get();
        }

        $models = (clone $relation)->whereKey($ids)->get();

        $unrelatedIDs = array_diff($ids, $models->modelKeys());
        if ($unrelatedIDs !== []) {
            throw new Error(self::unrelatedModel($unrelatedIDs));
        }

        return $models;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Relations\Relation<\Illuminate\Database\Eloquent\Model>  $relation
     * @param  array<mixed>  $ids
     */
    public static function delete(Relation $relation, array $ids): void
    {
        $models = self::findMany($relation, $ids);

        if ($relation instanceof BelongsToMany) {
            $relation->detach($ids);
        }

        foreach ($models as $model) {
            $model->delete();
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Relations\Relation<\Illuminate\Database\Eloquent\Model>|null  $relation
     * @param  int|string  $id
     */
    public static function findForUpsert(?Relation $relation, Model $model, mixed $id): ?Model
    {
        if (! self::applies($relation)) {
            return $model->newQuery()->find($id);
        }

        return self::firstForUpsert($relation, $model, [$model->getKeyName() => $id]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Relations\Relation<\Illuminate\Database\Eloquent\Model>|null  $relation
     * @param  array<string, mixed>  $columns
     */
    public static function firstForUpsert(?Relation $relation, Model $model, array $columns): ?Model
    {
        if (! self::applies($relation)) {
            return $model->newQuery()->firstWhere($columns);
        }

        $qualifiedColumns = [];
        foreach ($columns as $column => $value) {
            $qualifiedColumns[$model->qualifyColumn($column)] = $value;
        }

        $scopedModel = (clone $relation)->where($qualifiedColumns)->first();

        if ($scopedModel === null && $model->newQuery()->where($columns)->exists()) {
            throw new Error(self::unrelatedModel($columns));
        }

        return $scopedModel;
    }
}
