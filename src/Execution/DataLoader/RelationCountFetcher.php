<?php

namespace Nuwave\Lighthouse\Execution\DataLoader;

use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;

class RelationCountFetcher implements RelationFetcher
{
    /**
     * @var \Closure
     */
    private $decorateBuilder;

    public function __construct(Closure $decorateBuilder)
    {
        $this->decorateBuilder = $decorateBuilder;
    }

    public function fetch(EloquentCollection $parents, string $relationName): void
    {
        self::loadCount($parents, [$relationName => $this->decorateBuilder]);
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
            ->withAggregate($relations, '*', 'count')
            ->get()
            ->keyBy($parents->first()->getKeyName());

        $attributes = Arr::except(
            array_keys($models->first()->getAttributes()),
            $models->first()->getKeyName()
        );

        foreach ($parents as $model) {
            $extraAttributes = Arr::only($models->get($model->getKey())->getAttributes(), $attributes);

            $model->forceFill($extraAttributes)->syncOriginalAttributes($attributes);
        }
    }
}
