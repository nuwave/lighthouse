<?php

namespace Nuwave\Lighthouse\Execution\ModelsLoader;

use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AggregateModelsLoader implements ModelsLoader
{
    /**
     * @var string
     */
    protected $relation;

    /**
     * @var string
     */
    protected $column;

    /**
     * @var string
     */
    protected $function;

    /**
     * @var \Closure
     */
    protected $decorateBuilder;

    public function __construct(string $relation, string $column, string $function, Closure $decorateBuilder)
    {
        $this->relation = $relation;
        $this->column = $column;
        $this->function = $function;
        $this->decorateBuilder = $decorateBuilder;
    }

    /**
     * TODO use built-in function once switching to Laravel 8+ only
     * @see EloquentCollection::loadAggregate()
     */
    public function load(EloquentCollection $parents): void
    {
        if ($parents->isEmpty()) {
            return;
        }

        $models = $parents->first()->newModelQuery()
            ->whereKey($parents->modelKeys())
            ->select($parents->first()->getKeyName())
            ->withAggregate([$this->relation => $this->decorateBuilder], $this->column, $this->function)
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

    public function extract(Model $model)
    {
        /**
         * This is the name that Eloquent gives to the attribute that contains the aggregate.
         *
         * @see \Illuminate\Database\Eloquent\Concerns\QueriesRelationships::withAggregate()
         */
        $attribute = Str::snake(
            \Safe\preg_replace('/[^[:alnum:][:space:]_]/u', '', "$this->relation $this->function $this->column")
        );

        return $model->getAttribute($attribute);
    }
}
