<?php

namespace Nuwave\Lighthouse\Execution\ModelsLoader;

use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
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

    public function load(EloquentCollection $parents): void
    {
        // @phpstan-ignore-next-line Only present in Laravel 8+
        $parents->loadAggregate([$this->relation => $this->decorateBuilder], $this->column, $this->function);
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
