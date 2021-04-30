<?php

namespace Nuwave\Lighthouse\Execution\DataLoader;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

interface RelationLoader
{
    /**
     * Load the given relation on the given parent models.
     */
    public function load(EloquentCollection $parents, string $relationName): void;

    /**
     * Extract the result of loading the relation.
     *
     * @return mixed Probably a Model or a Collection thereof
     */
    public function extract(Model $model, string $relationName);
}
