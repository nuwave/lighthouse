<?php

namespace Nuwave\Lighthouse\Execution\DataLoader;

use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class RelationCountLoader implements RelationLoader
{
    /**
     * @var \Closure
     */
    private $decorateBuilder;

    public function __construct(Closure $decorateBuilder)
    {
        $this->decorateBuilder = $decorateBuilder;
    }

    public function load(EloquentCollection $parents, string $relationName): void
    {
        self::loadCount($parents, [$relationName => $this->decorateBuilder]);
    }

    public function extract(Model $model, string $relationName)
    {
        return self::extractCount($model, $relationName);
    }

    public static function extractCount(Model $model, string $relationName): int
    {
        /**
         * This is the name that Eloquent gives to the attribute that contains the count.
         *
         * @see \Illuminate\Database\Eloquent\Concerns\QueriesRelationships::withCount()
         */
        $countAttributeName = Str::snake("${relationName}_count");

        /**
         * We just assert this is an int and let PHP run into a type error if not.
         *
         * @var int $count
         */
        $count = $model->getAttribute($countAttributeName);

        return $count;
    }

    /**
     * Reload the models to get the `{relation}_count` attributes of models set.
     *
     * @deprecated Laravel 5.7 has native ->loadCount() on EloquentCollection
     * @see \Illuminate\Database\Eloquent\Collection::loadCount()
     *
     * @param  array<string, \Closure> $relations
     */
    public static function loadCount(EloquentCollection $parents, array $relations): void
    {
        if ($parents->isEmpty()) {
            return;
        }

        $models = $parents->first()->newModelQuery()
            ->whereKey($parents->modelKeys())
            ->select($parents->first()->getKeyName())
            ->withCount($relations)
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
