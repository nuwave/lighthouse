<?php

namespace Nuwave\Lighthouse\Execution\ModelsLoader;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

interface ModelsLoader
{
    /**
     * Load the result onto the given parent models.
     */
    public function load(EloquentCollection $parents): void;

    /**
     * Extract the result of loading from the given model.
     *
     * @return mixed Whatever was loaded
     */
    public function extract(Model $model);
}
