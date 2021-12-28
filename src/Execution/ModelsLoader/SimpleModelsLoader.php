<?php

namespace Nuwave\Lighthouse\Execution\ModelsLoader;

use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

class SimpleModelsLoader implements ModelsLoader
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
        $parents->load([$this->relation => $this->decorateBuilder]);
    }

    /**
     * Extract the relation that was loaded.
     *
     * @return mixed the model's relation
     */
    public function extract(Model $model)
    {
        // Dot notation may be used to eager load nested relations
        $parts = explode('.', $this->relation);

        // We just return the first level of relations for now.
        // They hold the nested relations in case they are needed.
        $firstRelation = $parts[0];

        return $model->getRelation($firstRelation);
    }
}
