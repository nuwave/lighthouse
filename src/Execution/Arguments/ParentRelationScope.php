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
    public const UNRELATED_MODEL = 'Cannot modify a model that is not related to the given parent.';

    /**
     * BelongsTo is excluded because its nested update and upsert attach unrelated models by design.
     *
     * @param  \Illuminate\Database\Eloquent\Relations\Relation<\Illuminate\Database\Eloquent\Model>|null  $relation
     */
    public static function applies(?Relation $relation): bool
    {
        // TODO remove the config check with v7 https://github.com/nuwave/lighthouse/issues/2744
        return $relation !== null
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

        assert($relation !== null, 'applies() is false for null.');

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

        if (array_diff($ids, $models->modelKeys()) !== []) {
            throw new Error(self::UNRELATED_MODEL);
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
     * Returns null if no model matches anywhere, so upsert can create one.
     *
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
     * Returns null if no model matches anywhere, so upsert can create one.
     *
     * @param  \Illuminate\Database\Eloquent\Relations\Relation<\Illuminate\Database\Eloquent\Model>|null  $relation
     * @param  array<string, mixed>  $columns
     */
    public static function firstForUpsert(?Relation $relation, Model $model, array $columns): ?Model
    {
        if (! self::applies($relation)) {
            return $model->newQuery()->firstWhere($columns);
        }

        assert($relation !== null, 'applies() is false for null.');

        $qualifiedColumns = [];
        foreach ($columns as $column => $value) {
            $qualifiedColumns[$model->qualifyColumn($column)] = $value;
        }

        $scopedModel = (clone $relation)->where($qualifiedColumns)->first();

        if ($scopedModel === null && $model->newQuery()->where($columns)->exists()) {
            throw new Error(self::UNRELATED_MODEL);
        }

        return $scopedModel;
    }
}
