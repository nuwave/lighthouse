<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution\ModelsLoader;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

interface ModelsLoader
{
    /**
     * Load the result onto the given parent models.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, TModel>  $parents
     */
    public function load(EloquentCollection $parents): void;

    /** Extract the result of loading from the given model. */
    public function extract(Model $model): mixed;
}
