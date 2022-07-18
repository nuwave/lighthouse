<?php

namespace Nuwave\Lighthouse\Execution\ModelsLoader;

use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CountModelsLoader implements ModelsLoader
{
    /**
     * @var string
     */
    protected $relation;

    /**
     * @var \Closure
     */
    protected $decorateBuilder;

    public function __construct(string $relation, Closure $decorateBuilder)
    {
        $this->relation = $relation;
        $this->decorateBuilder = $decorateBuilder;
    }

    public function load(EloquentCollection $parents): void
    {
        self::loadCount($parents, [$this->relation => $this->decorateBuilder]);
    }

    public function extract(Model $model): int
    {
        return self::extractCount($model, $this->relation);
    }

    public static function extractCount(Model $model, string $relationName): int
    {
        /**
         * This is the name that Eloquent gives to the attribute that contains the count.
         *
         * @see \Illuminate\Database\Eloquent\Concerns\QueriesRelationships::withCount()
         */
        $countAttributeName = Str::snake("{$relationName}_count");

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
     * @param  array<string, \Closure>  $relations
     */
    public static function loadCount(EloquentCollection $parents, array $relations): void
    {
        $firstParent = $parents->first();
        if (! $firstParent) {
            return;
        }

        $models = $firstParent->newModelQuery()
            ->whereKey($parents->modelKeys())
            ->select($firstParent->getKeyName())
            ->withCount($relations)
            ->get()
            ->keyBy($firstParent->getKeyName());

        $firstModel = $models->first();
        assert($firstModel instanceof Model);
        $attributes = Arr::except(
            array_keys($firstModel->getAttributes()),
            $firstModel->getKeyName()
        );

        foreach ($parents as $parent) {
            $model = $models->get($parent->getKey());
            assert($model instanceof Model);

            $extraAttributes = Arr::only($model->getAttributes(), $attributes);

            $parent->forceFill($extraAttributes);

            foreach ($attributes as $attribute) {
                $parent->syncOriginalAttribute($attribute);
            }
        }
    }
}
