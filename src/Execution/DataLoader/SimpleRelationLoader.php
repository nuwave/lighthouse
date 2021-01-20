<?php

namespace Nuwave\Lighthouse\Execution\DataLoader;

use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

class SimpleRelationLoader implements RelationLoader
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
        $parents->load([$relationName => $this->decorateBuilder]);
    }

    /**
     * Extract the relation that was loaded.
     *
     * @return mixed The model's relation.
     */
    public function extract(Model $model, string $relationName)
    {
        // Dot notation may be used to eager load nested relations
        $parts = explode('.', $relationName);

        // We just return the first level of relations for now. They
        // hold the nested relations in case they are needed.
        $firstRelation = $parts[0];

        return $model->getRelation($firstRelation);
    }
}
