<?php

namespace Nuwave\Lighthouse\Execution\ModelsLoader;

use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

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
    }

    public function extract(Model $model)
    {
    }
}
