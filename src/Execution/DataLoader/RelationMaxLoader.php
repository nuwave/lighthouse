<?php

namespace Nuwave\Lighthouse\Execution\DataLoader;

use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class RelationMaxLoader implements RelationAggregateLoader
{
    /**
     * @var \Closure
     */
    protected $decorateBuilder;

    public function __construct(Closure $decorateBuilder)
    {
        $this->decorateBuilder = $decorateBuilder;
    }

    public function load(EloquentCollection $parents, string $relationName, string $column): void
    {
        self::loadMax($parents, [$relationName => $this->decorateBuilder], $column);
    }

    public function extract(Model $model, string $relationName, string $column)
    {
        return self::extractMax($model, $relationName, $column);
    }

    public static function extractMax(Model $model, string $relationName, string $column): int
    {
        /**
         * This is the name that Eloquent gives to the attribute that contains the min.
         *
         * @see \Illuminate\Database\Eloquent\Concerns\QueriesRelationships::withMax()
         */
        $maxAttributeName = Str::snake("${relationName}_max_${column}");

        /**
         * We just assert this is an int and let PHP run into a type error if not.
         *
         * @var int $max
         */
        $max = $model->getAttribute($maxAttributeName);

        return $max;
    }

    /**
     * Reload the models to get the `{relation}_max_{column}` attributes of models set.
     *
     * @param  array<string, \Closure> $relations
     */
    public static function loadMax(EloquentCollection $parents, array $relations, string $column): void
    {
        if ($parents->isEmpty()) {
            return;
        }

        $models = $parents->first()->newModelQuery()
            ->whereKey($parents->modelKeys())
            ->select($parents->first()->getKeyName())
            ->withMax($relations, $column)
            ->get()
            ->keyBy($parents->first()->getKeyName());

        $attributes = Arr::except(
            array_keys($models->first()->getAttributes()),
            $models->first()->getKeyName()
        );

        foreach ($parents as $model) {
            $extraAttributes = Arr::only($models->get($model->getKey())->getAttributes(), $attributes);

            $model->forceFill($extraAttributes);

            foreach ($attributes as $attribute) {
                $model->syncOriginalAttribute($attribute);
            }
        }
    }
}
