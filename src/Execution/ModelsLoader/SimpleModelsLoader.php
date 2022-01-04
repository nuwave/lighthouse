<?php

namespace Nuwave\Lighthouse\Execution\ModelsLoader;

use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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

    /**
     * Unique key under which to store the relation to eliminate duplicates.
     *
     * @var string
     */
    protected $key;

    public function __construct(string $relation, Closure $decorateBuilder)
    {
        $this->relation = $relation;
        $this->decorateBuilder = $decorateBuilder;
        $this->key = Str::uuid()->toString();
    }

    public function load(EloquentCollection $parents): void
    {
        // Dot notation may be used to eager load nested relations
        $parts = explode('.', $this->relation);

        // We just return the first level of relations for now.
        // They hold the nested relations in case they are needed.
        $firstRelation = $parts[0];

        $parents->load([$this->relation => $this->decorateBuilder]);

        foreach ($parents as $model) {
            $relation = $model->getRelation($firstRelation);
            $model->setRelation($this->key, $relation);
        }
    }

    /**
     * Extract the relation that was loaded.
     *
     * @return mixed the model's relation
     */
    public function extract(Model $model)
    {
        return $model->getRelation($this->key);
    }
}
