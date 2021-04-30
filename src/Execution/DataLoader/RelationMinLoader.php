<?php

namespace Nuwave\Lighthouse\Execution\DataLoader;

use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class RelationMinLoader implements RelationAggregateLoader
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
        self::loadMin($parents, [$relationName => $this->decorateBuilder], $column);
    }

    public function extract(Model $model, string $relationName, string $column)
    {
        return self::extractMin($model, $relationName, $column);
    }

    public static function extractMin(Model $model, string $relationName, string $column): int
    {
        /**
         * This is the name that Eloquent gives to the attribute that contains the min.
         *
         * @see \Illuminate\Database\Eloquent\Concerns\QueriesRelationships::withMin()
         */
        $minAttributeName = Str::snake("${relationName}_min_${column}");

        /**
         * We just assert this is an int and let PHP run into a type error if not.
         *
         * @var int $min
         */
        $min = $model->getAttribute($minAttributeName);

        return $min;
    }

    /**
     * Reload the models to get the `{relation}_min_{column}` attributes of models set.
     *
     * @param  array<string, \Closure> $relations
     */
    public static function loadMin(EloquentCollection $parents, array $relations, string $column): void
    {
        if ($parents->isEmpty()) {
            return;
        }

        $models = $parents->first()->newModelQuery()
            ->whereKey($parents->modelKeys())
            ->select($parents->first()->getKeyName())
            ->withMin($relations, $column)
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
