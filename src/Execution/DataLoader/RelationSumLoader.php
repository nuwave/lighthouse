<?php

namespace Nuwave\Lighthouse\Execution\DataLoader;

use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class RelationSumLoader implements RelationLoader
{
    /**
     * @var \Closure
     */
    protected $decorateBuilder;

    public function __construct(Closure $decorateBuilder)
    {
        $this->decorateBuilder = $decorateBuilder;
    }

    public function load(EloquentCollection $parents, string $relationName,?string $column): void
    {
        self::loadSum($parents, [$relationName => $this->decorateBuilder],$column);
    }

    public function extract(Model $model, string $relationName,?string $column)
    {
        return self::extractSum($model, $relationName,$column);
    }

    public static function extractSum(Model $model, string $relationName,?string $column): int
    {
        /**
         * This is the name that Eloquent gives to the attribute that contains the sum.
         *
         * @see \Illuminate\Database\Eloquent\Concerns\QueriesRelationships::withSum()
         */
        $sumAttributeName = Str::snake("${relationName}_sum_${column}");

        /**
         * We just assert this is an int and let PHP run into a type error if not.
         *
         * @var int $sum
         */
        $sum = $model->getAttribute($sumAttributeName);

        return $sum;
    }

    /**
     * Reload the models to get the `{relation}_sum_{column}` attributes of models set.
     *
     * @param  array<string, \Closure> $relations
     */
    public static function loadSum(EloquentCollection $parents, array $relations,?string $column): void
    {
        if ($parents->isEmpty()) {
            return;
        }

        $models = $parents->first()->newModelQuery()
            ->whereKey($parents->modelKeys())
            ->select($parents->first()->getKeyName())
            ->withSum($relations,$column)
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
